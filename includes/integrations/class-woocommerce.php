<?php
/**
 * WooCommerce Integration Class
 * 
 * Handles WooCommerce-specific hooks and filters
 * Supports exchange rate conversion for Yoco payment gateway (ZAR only)
 * 
 * IMPORTANT: WooCommerce cart always uses ZAR internally.
 * Currency switching is only for DISPLAY purposes.
 * Yoco only accepts ZAR payments.
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
     * Flag to prevent recursive price filtering
     */
    private $filtering_price = false;
    
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
        // Change displayed currency symbol
        add_filter('woocommerce_currency_symbol', [$this, 'set_currency_symbol'], 10, 2);
        
        // Change displayed currency code
        add_filter('woocommerce_currency', [$this, 'change_displayed_currency'], 99);
        
        // Filter DISPLAYED price HTML (not the actual price)
        add_filter('woocommerce_get_price_html', [$this, 'filter_price_html'], 99, 2);
        
        // Add order meta data for currency tracking
        add_action('woocommerce_checkout_create_order', [$this, 'add_order_meta']);
        
        // Display conversion info in order admin
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_conversion_info']);
        
        // Add conversion info to order emails
        add_action('woocommerce_email_after_order_table', [$this, 'email_conversion_info'], 10, 4);
        
        // Cart item price display (for mini cart, cart page)
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 99, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 99, 3);
        add_filter('woocommerce_cart_subtotal', [$this, 'filter_cart_subtotal'], 99, 3);
        add_filter('woocommerce_cart_total', [$this, 'filter_cart_total'], 99);
        
        // IMPORTANT: Convert cart prices for checkout payment
        // This converts the displayed currency price back to ZAR for Yoco payment
        add_action('woocommerce_before_calculate_totals', [$this, 'convert_cart_prices_to_zar'], 99);
        
        // Checkout notice
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 5);
    }
    
    /**
     * Set currency symbol for display
     */
    public function set_currency_symbol($symbol, $currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $symbol;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $symbol;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        // Return the symbol for the display currency
        if ($currency === $display_currency) {
            return SDMC_Currency::get_symbol($display_currency);
        }
        
        return $symbol;
    }
    
    /**
     * Change displayed currency code
     */
    public function change_displayed_currency($currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $currency;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $currency;
        }
        
        // Check if we're on checkout - always show ZAR there for Yoco compatibility
        if (function_exists('is_checkout') && is_checkout()) {
            return $this->base_currency;
        }
        
        return SDMC_Currency::get_currency();
    }
    
    /**
     * Filter price HTML for display
     */
    public function filter_price_html($price_html, $product) {
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        // Don't filter on checkout - Yoco needs ZAR
        if (function_exists('is_checkout') && is_checkout()) {
            return $price_html;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $price_html;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($display_currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get the currency-specific price
        $product_id = $product->get_id();
        if (method_exists($product, 'is_type') && $product->is_type('variation')) {
            $product_id = $product->get_parent_id();
        }
        
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($display_currency), true);
        
        if (empty($currency_price) || !is_numeric($currency_price)) {
            return $price_html;
        }
        
        // Format the price in the display currency
        $symbol = SDMC_Currency::get_symbol($display_currency);
        $formatted_price = $symbol . number_format((float)$currency_price, 2);
        
        // Replace the price in the HTML
        return '<span class="woocommerce-Price-amount amount">' . $formatted_price . '</span>';
    }
    
    /**
     * Filter cart item price for display
     */
    public function filter_cart_item_price($price, $cart_item, $cart_item_key) {
        // Don't filter on checkout
        if (function_exists('is_checkout') && is_checkout()) {
            return $price;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $price;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $price;
        }
        
        $product_id = $cart_item['product_id'];
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($display_currency), true);
        
        if (empty($currency_price) || !is_numeric($currency_price)) {
            return $price;
        }
        
        $symbol = SDMC_Currency::get_symbol($display_currency);
        return $symbol . number_format((float)$currency_price, 2);
    }
    
    /**
     * Filter cart item subtotal for display
     */
    public function filter_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        // Don't filter on checkout
        if (function_exists('is_checkout') && is_checkout()) {
            return $subtotal;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $subtotal;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $subtotal;
        }
        
        $product_id = $cart_item['product_id'];
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($display_currency), true);
        
        if (empty($currency_price) || !is_numeric($currency_price)) {
            return $subtotal;
        }
        
        $quantity = $cart_item['quantity'];
        $total = (float)$currency_price * $quantity;
        
        $symbol = SDMC_Currency::get_symbol($display_currency);
        return $symbol . number_format($total, 2);
    }
    
    /**
     * Filter cart subtotal for display (in CART TOTALS section)
     */
    public function filter_cart_subtotal($subtotal, $compound, $cart) {
        // Don't filter on checkout
        if (function_exists('is_checkout') && is_checkout()) {
            return $subtotal;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $subtotal;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $subtotal;
        }
        
        // Calculate subtotal from cart items
        $cart_subtotal = 0;
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($display_currency), true);
            
            if (!empty($currency_price) && is_numeric($currency_price)) {
                $cart_subtotal += (float)$currency_price * $cart_item['quantity'];
            }
        }
        
        if ($cart_subtotal > 0) {
            $symbol = SDMC_Currency::get_symbol($display_currency);
            return $symbol . number_format($cart_subtotal, 2);
        }
        
        return $subtotal;
    }
    
    /**
     * Convert cart prices to ZAR for checkout/payment
     * 
     * This is the KEY function for Yoco compatibility:
     * - Customer sees prices in their selected currency (e.g., $35 USD)
     * - At checkout, we convert that $35 back to ZAR using exchange rate
     * - Yoco charges the converted ZAR amount
     * 
     * @param WC_Cart $cart
     */
    public function convert_cart_prices_to_zar($cart) {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        if (!class_exists('SDMC_Currency') || !class_exists('SDMC_Exchange_Rates')) {
            return;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        // No conversion needed if customer is using ZAR
        if ($display_currency === $this->base_currency) {
            return;
        }
        
        // Get the exchange rate for conversion
        $rate = SDMC_Exchange_Rates::get_rate($display_currency);
        
        if (!$rate || $rate <= 0) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            // Get the currency-specific price (e.g., $35 for USD)
            $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($display_currency), true);
            
            if (!empty($currency_price) && is_numeric($currency_price)) {
                // Convert the currency price back to ZAR
                // Rate format: 1 ZAR = X units of currency
                // So: ZAR amount = currency price / rate
                $zar_price = (float) $currency_price / $rate;
                
                // Set the cart item price to the converted ZAR amount
                $cart_item['data']->set_price($zar_price);
            }
            // If no currency-specific price is set, the original ZAR price is used
        }
    }
    
    /**
     * Filter cart total for display
     */
    public function filter_cart_total($total) {
        // Don't filter on checkout
        if (function_exists('is_checkout') && is_checkout()) {
            return $total;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $total;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $total;
        }
        
        // Calculate total from cart items
        $cart_total = 0;
        if (function_exists('WC') && isset(WC()->cart)) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($display_currency), true);
                
                if (!empty($currency_price) && is_numeric($currency_price)) {
                    $cart_total += (float)$currency_price * $cart_item['quantity'];
                }
            }
        }
        
        if ($cart_total > 0) {
            $symbol = SDMC_Currency::get_symbol($display_currency);
            return '<strong>' . $symbol . number_format($cart_total, 2) . '</strong>';
        }
        
        return $total;
    }
    
    /**
     * Add order meta data for currency tracking
     */
    public function add_order_meta($order) {
        if (!class_exists('SDMC_Currency')) {
            return;
        }
        
        $customer_currency = SDMC_Currency::get_currency();
        
        if ($customer_currency === $this->base_currency) {
            return;
        }
        
        // Store the currency the customer was viewing
        $order->update_meta_data('_sdmc_customer_currency', $customer_currency);
        $order->update_meta_data('_sdmc_base_currency', $this->base_currency);
        
        // Get exchange rate at time of order
        if (class_exists('SDMC_Exchange_Rates')) {
            $rate = SDMC_Exchange_Rates::get_rate($customer_currency);
            $order->update_meta_data('_sdmc_exchange_rate', $rate);
            $order->update_meta_data('_sdmc_rate_last_update', SDMC_Exchange_Rates::get_last_update());
            
            // Store the inverse rate for easier reference (1 USD = X ZAR)
            $inverse_rate = SDMC_Exchange_Rates::get_inverse_rate($customer_currency);
            $order->update_meta_data('_sdmc_inverse_rate', $inverse_rate);
            
            // Store the original currency prices for each item
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($customer_currency), true);
                if (!empty($currency_price)) {
                    $item->update_meta_data('_sdmc_original_currency', $customer_currency);
                    $item->update_meta_data('_sdmc_original_price', $currency_price);
                }
            }
        }
    }
    
    /**
     * Display checkout notice
     */
    public function checkout_notice() {
        $settings = get_option('sdmc_settings', []);
        
        if (empty($settings['checkout_notice'])) {
            return;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return;
        }
        
        $customer_currency = SDMC_Currency::get_currency();
        
        if ($customer_currency === $this->base_currency) {
            return;
        }
        
        // Get exchange rate and show conversion info
        $rate_info = '';
        if (class_exists('SDMC_Exchange_Rates')) {
            $inverse_rate = SDMC_Exchange_Rates::get_inverse_rate($customer_currency);
            if ($inverse_rate) {
                $rate_info = sprintf(
                    ' (1 %s ≈ %s ZAR)',
                    $customer_currency,
                    number_format($inverse_rate, 2)
                );
            }
        }
        
        // Calculate cart total in customer's currency
        $cart_total_currency = 0;
        if (function_exists('WC') && isset(WC()->cart)) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($customer_currency), true);
                if (!empty($currency_price) && is_numeric($currency_price)) {
                    $cart_total_currency += (float)$currency_price * $cart_item['quantity'];
                }
            }
        }
        
        $symbol = SDMC_Currency::get_symbol($customer_currency);
        
        echo '<div class="woocommerce-info sdmc-checkout-currency-notice">';
        if ($cart_total_currency > 0) {
            echo sprintf(
                esc_html__('You were viewing prices in %s. Your cart total is %s%s. Payment will be processed in ZAR (South African Rand) via Yoco.%s', 'sd-multicurrency-pro'),
                '<strong>' . esc_html($customer_currency) . '</strong>',
                '<strong>' . $symbol . number_format($cart_total_currency, 2) . '</strong>',
                esc_html($rate_info),
                ''
            );
        } else {
            echo sprintf(
                esc_html__('You were viewing prices in %s. Payment will be processed in ZAR (South African Rand) via Yoco.', 'sd-multicurrency-pro'),
                '<strong>' . esc_html($customer_currency) . '</strong>'
            );
        }
        echo '</div>';
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
        $inverse_rate = $order->get_meta('_sdmc_inverse_rate');
        $last_update = $order->get_meta('_sdmc_rate_last_update');
        
        ?>
        <div class="sdmc-order-conversion-info" style="background: #f6f7f7; padding: 12px; margin-top: 10px; border-radius: 4px;">
            <h4 style="margin: 0 0 10px 0;"><?php _e('Currency Conversion Details', 'sd-multicurrency-pro'); ?></h4>
            <p style="margin: 0 0 5px 0;">
                <strong><?php _e('Customer Currency:', 'sd-multicurrency-pro'); ?></strong> 
                <?php echo esc_html($customer_currency); ?>
            </p>
            <?php if ($inverse_rate) : ?>
            <p style="margin: 0 0 5px 0;">
                <strong><?php _e('Exchange Rate:', 'sd-multicurrency-pro'); ?></strong> 
                1 <?php echo esc_html($customer_currency); ?> = <?php echo esc_html(number_format((float)$inverse_rate, 2)); ?> <?php echo esc_html($this->base_currency); ?>
            </p>
            <?php endif; ?>
            <p style="margin: 0 0 5px 0;">
                <strong><?php _e('Amount Charged:', 'sd-multicurrency-pro'); ?></strong> 
                R<?php echo esc_html(number_format((float)$zar_amount, 2)); ?> <?php echo esc_html($this->base_currency); ?>
            </p>
            <?php 
            // Show original prices for each item
            $has_item_prices = false;
            foreach ($order->get_items() as $item) {
                $original_price = $item->get_meta('_sdmc_original_price');
                if (!empty($original_price)) {
                    $has_item_prices = true;
                    break;
                }
            }
            if ($has_item_prices) : ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <strong><?php _e('Original Prices:', 'sd-multicurrency-pro'); ?></strong>
                <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                    <?php foreach ($order->get_items() as $item) :
                        $original_price = $item->get_meta('_sdmc_original_price');
                        if (!empty($original_price)) :
                            $symbol = SDMC_Currency::get_symbol($customer_currency);
                    ?>
                    <li><?php echo esc_html($item->get_name()); ?>: <?php echo esc_html($symbol . number_format((float)$original_price, 2)); ?> <?php echo esc_html($customer_currency); ?></li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($last_update) : ?>
            <p style="margin: 10px 0 0 0; font-size: 11px; color: #666;">
                <em><?php _e('Rate updated:', 'sd-multicurrency-pro'); ?> <?php echo esc_html($last_update); ?></em>
            </p>
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
        
        if ($plain_text) {
            echo "\n" . __('--- Currency Conversion ---', 'sd-multicurrency-pro') . "\n";
            echo sprintf(__('You viewed prices in: %s', 'sd-multicurrency-pro'), $customer_currency) . "\n";
            echo sprintf(__('Amount charged: R%s ZAR', 'sd-multicurrency-pro'), number_format((float)$zar_amount, 2)) . "\n";
            echo "\n";
        } else {
            echo '<div style="margin: 20px 0; padding: 15px; background: #f6f7f7; border-radius: 4px;">';
            echo '<p style="margin: 0;">';
            echo sprintf(
                __('You viewed prices in %s. Your card was charged in ZAR (South African Rand).', 'sd-multicurrency-pro'),
                '<strong>' . esc_html($customer_currency) . '</strong>'
            );
            echo '</p>';
            echo '</div>';
        }
    }
}
