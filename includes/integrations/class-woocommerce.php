<?php
/**
 * WooCommerce Integration Class
 * 
 * Handles WooCommerce-specific hooks and filters
 * Supports exchange rate conversion for Yoco payment gateway (ZAR only)
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Integrations_Woocommerce {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Settings
     */
    private $settings = [];
    
    /**
     * Base currency
     */
    private $base_currency = 'ZAR';
    
    /**
     * Current customer currency (for order meta)
     */
    private $customer_currency = null;
    
    /**
     * Conversion data for order meta
     */
    private $conversion_data = [];
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        $this->settings = get_option('sdmc_settings', []);
        $this->base_currency = $this->settings['base_currency'] ?? 'ZAR';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Currency symbol filter - change symbol based on selected currency
        add_filter('woocommerce_currency_symbol', [$this, 'set_currency_symbol'], 10, 2);
        
        // Checkout - convert foreign currency prices back to ZAR for Yoco payment
        add_action('woocommerce_before_calculate_totals', [$this, 'convert_checkout_to_zar']);
        
        // Add order meta data for currency conversion tracking
        add_action('woocommerce_checkout_create_order', [$this, 'add_order_meta']);
        
        // Display conversion info in order admin
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_conversion_info']);
        
        // Add conversion info to order emails
        add_action('woocommerce_email_after_order_table', [$this, 'email_conversion_info'], 10, 4);
        
        // Filter product price on frontend to show currency-specific price
        add_filter('woocommerce_product_get_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_sale_price', [$this, 'get_product_price'], 99, 2);
        
        // Change displayed currency
        add_filter('woocommerce_currency', [$this, 'change_woocommerce_currency'], 99);
    }
    
    /**
     * Set currency symbol
     */
    public function set_currency_symbol($symbol, $currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $symbol;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $symbol;
        }
        
        // Get the user's selected display currency
        $display_currency = SDMC_Currency::get_currency();
        
        // If display currency matches the requested currency, return its symbol
        if ($currency === $display_currency) {
            return SDMC_Currency::get_symbol($display_currency);
        }
        
        return $symbol;
    }
    
    /**
     * Convert foreign currency prices to ZAR at checkout for Yoco payment
     * 
     * This is the key function that handles the conversion flow:
     * 1. Customer sees price in their selected currency (e.g., $50 USD)
     * 2. At checkout, we convert that USD price back to ZAR
     * 3. Yoco receives the ZAR amount for payment processing
     */
    public function convert_checkout_to_zar($cart) {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Only run at checkout
        if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
            return;
        }
        
        if (!class_exists('SDMC_Currency') || !class_exists('SDMC_Exchange_Rates')) {
            return;
        }
        
        // Get current display currency
        $current_currency = SDMC_Currency::get_currency();
        
        // Skip if already base currency (ZAR)
        if ($current_currency === $this->base_currency) {
            return;
        }
        
        // Store customer currency for order meta
        $this->customer_currency = $current_currency;
        
        // Get exchange rate
        $exchange_rate = SDMC_Exchange_Rates::get_rate($current_currency);
        
        if (!$exchange_rate) {
            // Log error but continue with fallback
            error_log("SDMC: No exchange rate found for $current_currency, using stored ZAR price");
        }
        
        // Set cart prices to ZAR equivalent for Yoco payment
        if (is_object($cart) && method_exists($cart, 'get_cart')) {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if (!isset($cart_item['data'])) {
                    continue;
                }
                
                $product = $cart_item['data'];
                $product_id = $product->get_id();
                
                // Get the price in customer's selected currency
                $foreign_price = get_post_meta($product_id, '_sd_price_' . strtolower($current_currency), true);
                
                // Fallback to base currency price if no foreign price set
                if (empty($foreign_price) || !is_numeric($foreign_price)) {
                    $foreign_price = get_post_meta($product_id, '_sd_price_' . strtolower($this->base_currency), true);
                    
                    // Final fallback to regular price
                    if (empty($foreign_price)) {
                        $foreign_price = $product->get_regular_price();
                    }
                    
                    // If still no price, skip
                    if (empty($foreign_price)) {
                        continue;
                    }
                    
                    // Customer is paying in foreign currency but product has no foreign price
                    // Use the stored ZAR price directly
                    $zar_amount = (float) $foreign_price;
                } else {
                    // Convert foreign currency price to ZAR for Yoco
                    if ($exchange_rate) {
                        // Rate format: 1 ZAR = X units of foreign currency
                        // To convert foreign to ZAR: foreign_price / rate
                        $zar_amount = (float) $foreign_price / $exchange_rate;
                        $zar_amount = round($zar_amount, 2);
                    } else {
                        // No exchange rate - use stored ZAR price as fallback
                        $zar_price = get_post_meta($product_id, '_sd_price_' . strtolower($this->base_currency), true);
                        $zar_amount = !empty($zar_price) ? (float) $zar_price : (float) $foreign_price;
                    }
                }
                
                // Set the product price to ZAR amount for Yoco
                if ($zar_amount > 0) {
                    $product->set_price($zar_amount);
                    
                    // Store conversion data for order meta
                    $this->conversion_data[$cart_item_key] = [
                        'original_currency' => $current_currency,
                        'original_price' => (float) $foreign_price,
                        'zar_price' => $zar_amount,
                        'exchange_rate' => $exchange_rate ?: null,
                    ];
                }
            }
        }
    }
    
    /**
     * Add currency conversion meta data to order
     */
    public function add_order_meta($order) {
        if (empty($this->customer_currency) || $this->customer_currency === $this->base_currency) {
            return;
        }
        
        // Add general currency info
        $order->update_meta_data('_sdmc_customer_currency', $this->customer_currency);
        $order->update_meta_data('_sdmc_base_currency', $this->base_currency);
        
        // Add conversion details for each item
        if (!empty($this->conversion_data)) {
            $order->update_meta_data('_sdmc_conversion_data', $this->conversion_data);
        }
        
        // Add exchange rate at time of order
        if (class_exists('SDMC_Exchange_Rates')) {
            $rate = SDMC_Exchange_Rates::get_rate($this->customer_currency);
            $order->update_meta_data('_sdmc_exchange_rate', $rate);
            $order->update_meta_data('_sdmc_rate_last_update', SDMC_Exchange_Rates::get_last_update());
        }
    }
    
    /**
     * Display conversion info in admin order view
     */
    public function display_order_conversion_info($order) {
        $customer_currency = $order->get_meta('_sdmc_customer_currency');
        
        if (empty($customer_currency) || $customer_currency === $this->base_currency) {
            return;
        }
        
        $zar_amount = $order->get_total();
        $original_rate = $order->get_meta('_sdmc_exchange_rate');
        $last_update = $order->get_meta('_sdmc_rate_last_update');
        $conversion_data = $order->get_meta('_sdmc_conversion_data');
        
        ?>
        <div class="sdmc-order-conversion-info" style="background: #f6f7f7; padding: 12px; margin-top: 10px; border-radius: 4px;">
            <h4 style="margin: 0 0 10px 0;"><?php _e('Currency Conversion Details', 'sd-multicurrency-pro'); ?></h4>
            <p style="margin: 0 0 5px 0;">
                <strong><?php _e('Customer Currency:', 'sd-multicurrency-pro'); ?></strong> 
                <?php echo esc_html($customer_currency); ?>
            </p>
            <p style="margin: 0 0 5px 0;">
                <strong><?php _e('Exchange Rate:', 'sd-multicurrency-pro'); ?></strong> 
                1 <?php echo esc_html($this->base_currency); ?> = <?php echo esc_html(number_format($original_rate, 4)); ?> <?php echo esc_html($customer_currency); ?>
            </p>
            <p style="margin: 0 0 5px 0;">
                <strong><?php _e('Amount Charged:', 'sd-multicurrency-pro'); ?></strong> 
                R<?php echo esc_html(number_format($zar_amount, 2)); ?> <?php echo esc_html($this->base_currency); ?>
            </p>
            <?php if ($last_update) : ?>
            <p style="margin: 0; font-size: 11px; color: #666;">
                <em><?php _e('Rate updated:', 'sd-multicurrency-pro'); ?> <?php echo esc_html($last_update); ?></em>
            </p>
            <?php endif; ?>
            
            <?php if (!empty($conversion_data)) : ?>
            <table style="width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #e5e5e5;">
                        <th style="padding: 5px; text-align: left;"><?php _e('Item', 'sd-multicurrency-pro'); ?></th>
                        <th style="padding: 5px; text-align: right;"><?php echo esc_html($customer_currency); ?> Price</th>
                        <th style="padding: 5px; text-align: right;">ZAR Charged</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $item_index = 0;
                    foreach ($order->get_items() as $item_id => $item) {
                        $item_key = array_keys($conversion_data)[$item_index] ?? null;
                        $conv = $item_key ? ($conversion_data[$item_key] ?? null) : null;
                        ?>
                        <tr>
                            <td style="padding: 5px;"><?php echo esc_html($item->get_name()); ?></td>
                            <td style="padding: 5px; text-align: right;">
                                <?php 
                                if ($conv) {
                                    $symbol = class_exists('SDMC_Currency') ? SDMC_Currency::get_symbol($customer_currency) : '';
                                    echo esc_html($symbol . number_format($conv['original_price'], 2));
                                }
                                ?>
                            </td>
                            <td style="padding: 5px; text-align: right;">
                                R<?php echo esc_html(number_format($conv ? $conv['zar_price'] : 0, 2)); ?>
                            </td>
                        </tr>
                        <?php
                        $item_index++;
                    }
                    ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add conversion info to order emails
     */
    public function email_conversion_info($order, $sent_to_admin, $plain_text, $email) {
        $customer_currency = $order->get_meta('_sdmc_customer_currency');
        
        if (empty($customer_currency) || $customer_currency === $this->base_currency) {
            return;
        }
        
        $zar_amount = $order->get_total();
        $original_rate = $order->get_meta('_sdmc_exchange_rate');
        
        if ($plain_text) {
            echo "\n" . __('--- Currency Conversion ---', 'sd-multicurrency-pro') . "\n";
            echo sprintf(__('You viewed prices in: %s', 'sd-multicurrency-pro'), $customer_currency) . "\n";
            echo sprintf(__('Amount charged: R%s ZAR', 'sd-multicurrency-pro'), number_format($zar_amount, 2)) . "\n";
            echo "\n";
        } else {
            echo '<div style="margin: 20px 0; padding: 15px; background: #f6f7f7; border-radius: 4px;">';
            echo '<h4 style="margin: 0 0 10px 0;">' . esc_html__('Currency Conversion', 'sd-multicurrency-pro') . '</h4>';
            echo '<p style="margin: 0;">';
            echo sprintf(
                __('You viewed prices in %s. Your card was charged in ZAR (South African Rand).', 'sd-multicurrency-pro'),
                '<strong>' . esc_html($customer_currency) . '</strong>'
            );
            echo '</p>';
            echo '<p style="margin: 10px 0 0 0;"><strong>' . esc_html__('Amount Charged:', 'sd-multicurrency-pro') . '</strong> R' . esc_html(number_format($zar_amount, 2)) . ' ZAR</p>';
            echo '</div>';
        }
    }
    
    /**
     * Get product price in current currency for frontend display
     */
    public function get_product_price($price, $product) {
        // Skip in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }
        
        // Skip if no product
        if (!$product) {
            return $price;
        }
        
        // Get current currency
        if (!class_exists('SDMC_Currency')) {
            return $price;
        }
        
        $current_currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($current_currency === $this->base_currency) {
            return $price;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        
        // Check for variation parent
        if (is_object($product) && method_exists($product, 'is_type') && $product->is_type('variation')) {
            $product_id = $product->get_parent_id();
        }
        
        // Get currency-specific price
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($current_currency), true);
        
        // Return the currency-specific price if set
        if (!empty($currency_price) && is_numeric($currency_price)) {
            return wc_format_decimal($currency_price);
        }
        
        // No currency price set - return original price
        return $price;
    }
    
    /**
     * Change WooCommerce currency for display
     */
    public function change_woocommerce_currency($currency) {
        // Skip in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $currency;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $currency;
        }
        
        return SDMC_Currency::get_currency();
    }
}
