<?php
/**
 * WooCommerce Integration Class
 * 
 * Handles WooCommerce-specific hooks and filters
 * Supports exchange rate conversion for Yoco payment gateway (ZAR only)
 * 
 * IMPORTANT CONCEPT:
 * - Customer SEES prices in their selected currency (e.g., $35 USD) throughout the ENTIRE site
 * - Price Priority:
 *   1. Use currency-specific price if set (e.g., _sd_price_gbp = 25)
 *   2. Fall back to USD price and convert (e.g., _sd_price_usd = 35 -> convert to GBP)
 *   3. Last resort: convert from base ZAR price using exchange rate
 * - Internally, cart prices are converted to ZAR for Yoco payment processing
 * - Yoco receives and charges the correct ZAR amount
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
        // Change displayed currency symbol - show selected currency symbol everywhere
        add_filter('woocommerce_currency_symbol', [$this, 'set_currency_symbol'], 10, 2);
        
        // Change displayed currency code - show selected currency code everywhere
        add_filter('woocommerce_currency', [$this, 'change_displayed_currency'], 99);
        
        // Filter DISPLAYED price HTML - show selected currency price
        add_filter('woocommerce_get_price_html', [$this, 'filter_price_html'], 99, 2);
        
        // Add order meta data for currency tracking
        add_action('woocommerce_checkout_create_order', [$this, 'add_order_meta']);
        
        // Display conversion info in order admin
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_conversion_info']);
        
        // Add conversion info to order emails
        add_action('woocommerce_email_after_order_table', [$this, 'email_conversion_info'], 10, 4);
        
        // Cart item price display - show selected currency price EVERYWHERE including checkout
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 99, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 99, 3);
        add_filter('woocommerce_cart_subtotal', [$this, 'filter_cart_subtotal'], 99, 3);
        add_filter('woocommerce_cart_total', [$this, 'filter_cart_total'], 99);
        
        // Coupon display - convert coupon amounts to display currency
        add_filter('woocommerce_cart_totals_coupon_html', [$this, 'filter_coupon_html'], 99, 3);
        
        // Coupon amount display in mini cart and other places
        add_filter('woocommerce_coupon_amount', [$this, 'filter_coupon_amount'], 99, 2);
        
        // Discount total display in cart
        add_filter('woocommerce_cart_totals_discount_total_html', [$this, 'filter_discount_total_html'], 99);
        
        // Convert cart fees for display
        add_filter('woocommerce_cart_totals_fee_html', [$this, 'filter_fee_html'], 99, 2);
        
        // Shipping display - convert shipping costs
        add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'filter_shipping_label'], 99, 2);
        add_filter('woocommerce_cart_totals_shipping_html', [$this, 'filter_shipping_html'], 99, 2);
        
        // Tax display - convert tax amounts
        add_filter('woocommerce_cart_totals_taxes_total_html', [$this, 'filter_tax_html'], 99);
        
        // Convert cart prices to ZAR for payment processing (Yoco needs ZAR)
        // This happens BEFORE totals are calculated, so Yoco gets the correct ZAR amount
        add_action('woocommerce_before_calculate_totals', [$this, 'convert_cart_prices_to_zar'], 999);
        
        // Ensure Yoco gateway always receives ZAR amounts
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_gateways']);
        
        // Fix order total for Yoco - ensure ZAR amount is sent
        add_filter('woocommerce_order_amount_total', [$this, 'ensure_zar_total_for_payment'], 999, 2);
        add_filter('woocommerce_order_amount_subtotal', [$this, 'ensure_zar_total_for_payment'], 999, 2);
        
        // Checkout notice (optional)
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 5);
        
        // Filter order item display in emails and thank you page
        add_filter('woocommerce_order_formatted_line_subtotal', [$this, 'filter_order_line_subtotal'], 99, 3);
        add_filter('woocommerce_get_formatted_order_total', [$this, 'filter_order_total'], 99, 2);
        
        // Filter order discount display
        add_filter('woocommerce_order_get_discount_total', [$this, 'filter_order_discount_total'], 99, 2);
        
        // Filter coupon line item display in order
        add_filter('woocommerce_coupon_discount_amount_html', [$this, 'filter_order_coupon_discount_html'], 99, 2);
    }
    
    /**
     * Get converted price for a product
     * 
     * Priority:
     * 1. Use currency-specific price if set (e.g., _sd_price_gbp = 25)
     * 2. Fall back to USD price and convert to target currency
     * 3. Last resort: convert from base ZAR price using exchange rate
     * 
     * @param int $product_id
     * @param string $currency
     * @return float|false
     */
    private function get_converted_price($product_id, $currency) {
        if ($currency === $this->base_currency) {
            // Return the base price
            $price = get_post_meta($product_id, '_price', true);
            return $price ? (float)$price : false;
        }
        
        // First, check for currency-specific price
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($currency), true);
        
        if (!empty($currency_price) && is_numeric($currency_price)) {
            return (float)$currency_price;
        }
        
        // Fallback 1: Check for USD price and convert to target currency
        if ($currency !== 'USD') {
            $usd_price = get_post_meta($product_id, '_sd_price_usd', true);
            
            if (!empty($usd_price) && is_numeric($usd_price) && class_exists('SDMC_Exchange_Rates')) {
                // Convert USD to target currency
                $converted = $this->convert_between_currencies((float)$usd_price, 'USD', $currency);
                if ($converted !== false) {
                    return $converted;
                }
            }
        }
        
        // Fallback 2: Convert from ZAR using exchange rate
        if (!class_exists('SDMC_Exchange_Rates')) {
            return false;
        }
        
        $zar_price = get_post_meta($product_id, '_price', true);
        
        if (empty($zar_price) || !is_numeric($zar_price)) {
            return false;
        }
        
        // Get exchange rate and convert
        $rate = SDMC_Exchange_Rates::get_rate($currency);
        
        if (!$rate || $rate <= 0) {
            return false;
        }
        
        // Rate format: 1 ZAR = X units of currency
        // So: currency amount = ZAR price * rate
        $converted_price = (float)$zar_price * $rate;
        
        return round($converted_price, 2);
    }
    
    /**
     * Convert amount from one currency to another
     * Uses ZAR as the intermediate currency for conversion
     * 
     * @param float $amount Amount in source currency
     * @param string $from_currency Source currency code
     * @param string $to_currency Target currency code
     * @return float|false Converted amount or false on failure
     */
    private function convert_between_currencies($amount, $from_currency, $to_currency) {
        if ($from_currency === $to_currency) {
            return (float)$amount;
        }
        
        if (!class_exists('SDMC_Exchange_Rates')) {
            return false;
        }
        
        $from_rate = SDMC_Exchange_Rates::get_rate($from_currency);
        $to_rate = SDMC_Exchange_Rates::get_rate($to_currency);
        
        if (!$from_rate || !$to_rate || $from_rate <= 0 || $to_rate <= 0) {
            return false;
        }
        
        // Rates are: 1 ZAR = X units of currency
        // Step 1: Convert from source currency to ZAR
        // amount_in_zar = amount / from_rate
        $zar_amount = (float)$amount / $from_rate;
        
        // Step 2: Convert from ZAR to target currency
        // target_amount = zar_amount * to_rate
        $target_amount = $zar_amount * $to_rate;
        
        return round($target_amount, 2);
    }
    
    /**
     * Set currency symbol for display
     * Shows the selected currency symbol everywhere (including checkout)
     * BUT returns ZAR symbol during payment processing for Yoco compatibility
     */
    public function set_currency_symbol($symbol, $currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $symbol;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $symbol;
        }
        
        // Check if we're in a payment processing context (Yoco needs ZAR)
        if ($this->is_payment_processing()) {
            return SDMC_Currency::get_symbol($this->base_currency);
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        // Return the symbol for the display currency
        return SDMC_Currency::get_symbol($display_currency);
    }
    
    /**
     * Change displayed currency code
     * Shows the selected currency code everywhere (including checkout)
     * BUT returns ZAR during payment processing for Yoco compatibility
     */
    public function change_displayed_currency($currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $currency;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $currency;
        }
        
        // Check if we're in a payment processing context (Yoco needs ZAR)
        if ($this->is_payment_processing()) {
            return $this->base_currency; // Return ZAR for Yoco
        }
        
        // Show selected currency for display
        return SDMC_Currency::get_currency();
    }
    
    /**
     * Check if we're in a payment processing context
     * Yoco gateway needs to see ZAR currency
     * 
     * @return bool
     */
    private function is_payment_processing() {
        // Check for Yoco-specific AJAX actions
        if (wp_doing_ajax()) {
            $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
            
            // Yoco payment processing actions
            $yoco_actions = [
                'woocommerce_ajax_update_order_review',
                'woocommerce_checkout',
                'wc_yoco_process_payment',
                'yoco_process_payment',
            ];
            
            if (in_array($action, $yoco_actions)) {
                return true;
            }
            
            // Check if this is a checkout-related AJAX call
            if (strpos($action, 'checkout') !== false || strpos($action, 'payment') !== false) {
                return true;
            }
        }
        
        // Check if we're processing checkout
        if (did_action('woocommerce_before_checkout_process') || did_action('woocommerce_checkout_process')) {
            return true;
        }
        
        // Check if we're in the Yoco gateway class context
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        foreach ($backtrace as $trace) {
            if (isset($trace['class']) && strpos($trace['class'], 'Yoco') !== false) {
                return true;
            }
            if (isset($trace['function']) && strpos($trace['function'], 'yoco') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Filter price HTML for display
     * Shows selected currency price on product pages, cart, checkout - everywhere
     */
    public function filter_price_html($price_html, $product) {
        if (is_admin() && !wp_doing_ajax()) {
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
        
        // Get product ID
        $product_id = $product->get_id();
        if (method_exists($product, 'is_type') && $product->is_type('variation')) {
            $product_id = $product->get_parent_id();
        }
        
        // Get converted price (currency-specific or converted from ZAR)
        $converted_price = $this->get_converted_price($product_id, $display_currency);
        
        if ($converted_price === false) {
            return $price_html;
        }
        
        // Format the price in the display currency
        $symbol = SDMC_Currency::get_symbol($display_currency);
        $formatted_price = $symbol . number_format($converted_price, 2);
        
        // Replace the price in the HTML
        return '<span class="woocommerce-Price-amount amount">' . $formatted_price . '</span>';
    }
    
    /**
     * Filter cart item price for display
     * Shows selected currency price - EVERYWHERE including checkout
     */
    public function filter_cart_item_price($price, $cart_item, $cart_item_key) {
        if (!class_exists('SDMC_Currency')) {
            return $price;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $price;
        }
        
        $product_id = $cart_item['product_id'];
        
        // Get converted price (currency-specific or converted from ZAR)
        $converted_price = $this->get_converted_price($product_id, $display_currency);
        
        if ($converted_price === false) {
            return $price;
        }
        
        $symbol = SDMC_Currency::get_symbol($display_currency);
        return $symbol . number_format($converted_price, 2);
    }
    
    /**
     * Filter cart item subtotal for display
     * Shows selected currency subtotal - EVERYWHERE including checkout
     */
    public function filter_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (!class_exists('SDMC_Currency')) {
            return $subtotal;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $subtotal;
        }
        
        $product_id = $cart_item['product_id'];
        
        // Get converted price (currency-specific or converted from ZAR)
        $converted_price = $this->get_converted_price($product_id, $display_currency);
        
        if ($converted_price === false) {
            return $subtotal;
        }
        
        $quantity = $cart_item['quantity'];
        $total = $converted_price * $quantity;
        
        $symbol = SDMC_Currency::get_symbol($display_currency);
        return $symbol . number_format($total, 2);
    }
    
    /**
     * Filter cart subtotal for display (in CART TOTALS section)
     * Shows selected currency subtotal - EVERYWHERE including checkout
     * This shows the subtotal BEFORE discounts are applied
     */
    public function filter_cart_subtotal($subtotal, $compound, $cart) {
        if (!class_exists('SDMC_Currency')) {
            return $subtotal;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $subtotal;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $subtotal;
        }
        
        // Calculate subtotal from cart items (before discounts)
        $cart_subtotal = 0;
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            // Get converted price (currency-specific or converted from ZAR)
            $converted_price = $this->get_converted_price($product_id, $display_currency);
            
            if ($converted_price !== false) {
                $cart_subtotal += $converted_price * $cart_item['quantity'];
            }
        }
        
        if ($cart_subtotal > 0) {
            $symbol = SDMC_Currency::get_symbol($display_currency);
            return $symbol . number_format($cart_subtotal, 2);
        }
        
        return $subtotal;
    }
    
    /**
     * Filter cart total for display
     * Shows selected currency total - EVERYWHERE including checkout
     * Properly accounts for coupons and discounts
     */
    public function filter_cart_total($total) {
        if (!class_exists('SDMC_Currency')) {
            return $total;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $total;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $total;
        }
        
        // Calculate total from cart items
        $cart_total = 0;
        if (function_exists('WC') && isset(WC()->cart)) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                
                // Get converted price (currency-specific or converted from ZAR)
                $converted_price = $this->get_converted_price($product_id, $display_currency);
                
                if ($converted_price !== false) {
                    $cart_total += $converted_price * $cart_item['quantity'];
                }
            }
            
            // Subtract coupon discounts (convert from ZAR to display currency)
            $discount_total_zar = WC()->cart->get_discount_total();
            if ($discount_total_zar > 0) {
                $discount_converted = $this->convert_amount_to_display_currency($discount_total_zar);
                $cart_total -= $discount_converted;
            }
            
            // Add shipping if applicable
            $shipping_total_zar = WC()->cart->get_shipping_total();
            if ($shipping_total_zar > 0) {
                $shipping_converted = $this->convert_amount_to_display_currency($shipping_total_zar);
                $cart_total += $shipping_converted;
            }
            
            // Add fees if any
            $fees = WC()->cart->get_fees();
            if (!empty($fees)) {
                foreach ($fees as $fee) {
                    $fee_converted = $this->convert_amount_to_display_currency($fee->amount);
                    $cart_total += $fee_converted;
                }
            }
            
            // Add taxes if applicable
            $tax_total = WC()->cart->get_taxes_total();
            if ($tax_total > 0) {
                $tax_converted = $this->convert_amount_to_display_currency($tax_total);
                $cart_total += $tax_converted;
            }
        }
        
        if ($cart_total > 0) {
            $symbol = SDMC_Currency::get_symbol($display_currency);
            return '<strong>' . $symbol . number_format(max(0, $cart_total), 2) . '</strong>';
        }
        
        return $total;
    }
    
    /**
     * Convert cart prices to ZAR for payment processing
     * 
     * THIS IS THE KEY FUNCTION:
     * - Customer sees $35 USD throughout the site
     * - This function converts $35 to ZAR (e.g., R648) for Yoco
     * - Yoco charges R648 ZAR
     * - Display filters still show $35 USD to customer
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
        
        // Prevent recursion
        if ($this->filtering_price) {
            return;
        }
        $this->filtering_price = true;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            
            // Get the ZAR price directly for payment
            $zar_price = $this->get_zar_price_for_payment($product_id, $display_currency);
            
            if ($zar_price !== false && $zar_price > 0) {
                // Set the cart item price to the ZAR amount for Yoco
                $cart_item['data']->set_price($zar_price);
            }
        }
        
        $this->filtering_price = false;
    }
    
    /**
     * Get the ZAR price for payment processing
     * 
     * This calculates the correct ZAR amount based on the price source:
     * 1. If currency-specific price is set → convert that to ZAR
     * 2. If USD price is set → convert USD to ZAR
     * 3. Otherwise → use the base ZAR price
     * 
     * @param int $product_id
     * @param string $display_currency
     * @return float|false
     */
    private function get_zar_price_for_payment($product_id, $display_currency) {
        // Get the base ZAR price
        $base_zar_price = get_post_meta($product_id, '_price', true);
        
        if (empty($base_zar_price) || !is_numeric($base_zar_price)) {
            return false;
        }
        $base_zar_price = (float)$base_zar_price;
        
        // If displaying in ZAR, no conversion needed
        if ($display_currency === $this->base_currency) {
            return $base_zar_price;
        }
        
        // Check if there's a currency-specific price set
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($display_currency), true);
        
        if (!empty($currency_price) && is_numeric($currency_price)) {
            // Convert the currency-specific price to ZAR
            $currency_price = (float)$currency_price;
            $rate = SDMC_Exchange_Rates::get_rate($display_currency);
            
            if ($rate && $rate > 0) {
                // Rate format: 1 ZAR = X units of currency
                // ZAR amount = currency price / rate
                return round($currency_price / $rate, 2);
            }
        }
        
        // Check if there's a USD price set (fallback currency)
        $usd_price = get_post_meta($product_id, '_sd_price_usd', true);
        
        if (!empty($usd_price) && is_numeric($usd_price)) {
            // Convert USD to ZAR
            $usd_price = (float)$usd_price;
            $usd_rate = SDMC_Exchange_Rates::get_rate('USD');
            
            if ($usd_rate && $usd_rate > 0) {
                // ZAR amount = USD price / USD rate
                return round($usd_price / $usd_rate, 2);
            }
        }
        
        // No currency-specific price, use base ZAR price
        return $base_zar_price;
    }
    
    /**
     * Filter available payment gateways
     * Ensures Yoco is always available regardless of display currency
     */
    public function filter_gateways($gateways) {
        return $gateways;
    }
    
    /**
     * Ensure ZAR total for payment gateways
     * This ensures Yoco receives the correct ZAR amount
     */
    public function ensure_zar_total_for_payment($amount, $order = null) {
        // Only modify during payment processing
        if (is_admin() && !wp_doing_ajax()) {
            return $amount;
        }
        
        // Return the original amount - it should already be in ZAR
        // This is just a safety filter
        return $amount;
    }
    
    // ==================== COUPON HANDLING ====================
    
    /**
     * Filter coupon discount HTML in cart totals
     * Shows coupon discount in selected currency (display only)
     * 
     * IMPORTANT: We only convert the DISPLAY, not the internal calculation.
     * WooCommerce calculates the coupon discount in ZAR internally,
     * we just show the converted amount to the customer.
     * 
     * @param string $html
     * @param WC_Coupon $coupon
     * @param string $discount_amount_html
     * @return string
     */
    public function filter_coupon_html($html, $coupon, $discount_amount_html) {
        if (!class_exists('SDMC_Currency')) {
            return $html;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $html;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $html;
        }
        
        // Get the actual discount amount from the cart (this is in ZAR)
        $discount_amount = 0;
        if (function_exists('WC') && isset(WC()->cart)) {
            $discount_amount = WC()->cart->get_coupon_discount_amount($coupon->get_code(), WC()->cart->display_cart_ex_tax);
        }
        
        if ($discount_amount > 0) {
            // Convert discount from ZAR to display currency
            $converted_discount = $this->convert_amount_to_display_currency($discount_amount);
            $symbol = SDMC_Currency::get_symbol($display_currency);
            
            $discount_amount_html = '-' . $symbol . number_format($converted_discount, 2);
            
            // Rebuild the HTML with converted amount
            $html = $discount_amount_html . ' <a href="' . esc_url(add_query_arg('remove_coupon', rawurlencode($coupon->get_code()), defined('WOOCOMMERCE_CHECKOUT') ? wc_get_checkout_url() : wc_get_cart_url())) . '" class="woocommerce-remove-coupon" data-coupon="' . esc_attr($coupon->get_code()) . '">' . __('[Remove]', 'woocommerce') . '</a>';
        }
        
        return $html;
    }
    
    /**
     * Filter coupon amount for display (for fixed coupons)
     * This handles the coupon value display in various places
     * 
     * @param float $amount The coupon amount
     * @param WC_Coupon $coupon The coupon object
     * @return float
     */
    public function filter_coupon_amount($amount, $coupon) {
        // Only filter on frontend, not admin
        if (is_admin() && !wp_doing_ajax()) {
            return $amount;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $amount;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $amount;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $amount;
        }
        
        // Only convert fixed amount coupons, not percentage coupons
        if ($coupon->get_discount_type() === 'percent') {
            return $amount;
        }
        
        // Convert fixed coupon amount from ZAR to display currency
        return $this->convert_amount_to_display_currency($amount);
    }
    
    /**
     * Filter discount total HTML in cart totals
     * Shows total discount in selected currency
     * 
     * @param string $html
     * @return string
     */
    public function filter_discount_total_html($html) {
        if (!class_exists('SDMC_Currency')) {
            return $html;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $html;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $html;
        }
        
        // Get total discount from cart (in ZAR)
        $discount_total = 0;
        if (function_exists('WC') && isset(WC()->cart)) {
            $discount_total = WC()->cart->get_discount_total();
        }
        
        if ($discount_total > 0) {
            $converted_discount = $this->convert_amount_to_display_currency($discount_total);
            $symbol = SDMC_Currency::get_symbol($display_currency);
            
            return '<span class="woocommerce-Price-amount amount">' . $symbol . number_format($converted_discount, 2) . '</span>';
        }
        
        return $html;
    }
    
    /**
     * Filter fee HTML in cart totals
     * Shows fees in selected currency
     * 
     * @param string $html
     * @param object $fee
     * @return string
     */
    public function filter_fee_html($html, $fee) {
        if (!class_exists('SDMC_Currency')) {
            return $html;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $html;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $html;
        }
        
        $fee_amount = $fee->amount;
        $converted_amount = $this->convert_amount_to_display_currency($fee_amount);
        $symbol = SDMC_Currency::get_symbol($display_currency);
        
        return $symbol . number_format($converted_amount, 2);
    }
    
    /**
     * Filter shipping method label
     * Shows shipping cost in selected currency
     * 
     * @param string $label
     * @param WC_Shipping_Method $method
     * @return string
     */
    public function filter_shipping_label($label, $method) {
        if (!class_exists('SDMC_Currency')) {
            return $label;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $label;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $label;
        }
        
        // Get the shipping cost
        $cost = $method->cost;
        
        if ($cost > 0) {
            $converted_cost = $this->convert_amount_to_display_currency($cost);
            $symbol = SDMC_Currency::get_symbol($display_currency);
            
            // Replace the price in the label
            $label = $method->get_label() . ': ' . $symbol . number_format($converted_cost, 2);
        } elseif ($cost == 0) {
            $label = $method->get_label() . ': ' . __('Free', 'woocommerce');
        }
        
        return $label;
    }
    
    /**
     * Filter shipping HTML in cart totals
     * 
     * @param string $html
     * @param array $packages
     * @return string
     */
    public function filter_shipping_html($html, $packages) {
        if (!class_exists('SDMC_Currency')) {
            return $html;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $html;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $html;
        }
        
        return $html;
    }
    
    /**
     * Filter tax HTML in cart totals
     * Shows tax in selected currency
     * 
     * @param string $html
     * @return string
     */
    public function filter_tax_html($html) {
        if (!class_exists('SDMC_Currency')) {
            return $html;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $html;
        }
        
        // Skip during payment processing
        if ($this->is_payment_processing()) {
            return $html;
        }
        
        // Get tax total from cart
        $tax_total = 0;
        if (function_exists('WC') && isset(WC()->cart)) {
            $tax_total = WC()->cart->get_taxes_total();
        }
        
        if ($tax_total > 0) {
            $converted_tax = $this->convert_amount_to_display_currency($tax_total);
            $symbol = SDMC_Currency::get_symbol($display_currency);
            
            return $symbol . number_format($converted_tax, 2);
        }
        
        return $html;
    }
    
    /**
     * Convert amount from ZAR to display currency
     * 
     * @param float $amount Amount in ZAR
     * @return float Amount in display currency
     */
    private function convert_amount_to_display_currency($amount) {
        if (!class_exists('SDMC_Currency') || !class_exists('SDMC_Exchange_Rates')) {
            return $amount;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        if ($display_currency === $this->base_currency) {
            return $amount;
        }
        
        $rate = SDMC_Exchange_Rates::get_rate($display_currency);
        
        if (!$rate || $rate <= 0) {
            return $amount;
        }
        
        // Rate format: 1 ZAR = X units of currency
        // So: currency amount = ZAR amount * rate
        return round((float)$amount * $rate, 2);
    }
    
    /**
     * Get the total cart discount in display currency
     * 
     * @return float
     */
    private function get_total_discount_in_display_currency() {
        if (!function_exists('WC') || !isset(WC()->cart)) {
            return 0;
        }
        
        $display_currency = SDMC_Currency::get_currency();
        
        // Get total discount from cart (in ZAR)
        $discount_total = WC()->cart->get_discount_total();
        
        if ($display_currency === $this->base_currency) {
            return $discount_total;
        }
        
        return $this->convert_amount_to_display_currency($discount_total);
    }
    
    /**
     * Filter order line subtotal for display on thank you page and emails
     * Shows selected currency price
     */
    public function filter_order_line_subtotal($subtotal, $item, $order) {
        if (is_admin() && !wp_doing_ajax()) {
            return $subtotal;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $subtotal;
        }
        
        $customer_currency = $order->get_meta('_sdmc_customer_currency');
        
        if (empty($customer_currency) || $customer_currency === $this->base_currency) {
            return $subtotal;
        }
        
        $original_price = $item->get_meta('_sdmc_original_price');
        
        if (empty($original_price)) {
            return $subtotal;
        }
        
        $symbol = SDMC_Currency::get_symbol($customer_currency);
        $quantity = $item->get_quantity();
        $total = (float)$original_price * $quantity;
        
        return $symbol . number_format($total, 2);
    }
    
    /**
     * Filter order total for display on thank you page and emails
     * Shows selected currency total (with coupon discount applied)
     */
    public function filter_order_total($total, $order) {
        if (is_admin() && !wp_doing_ajax()) {
            return $total;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $total;
        }
        
        $customer_currency = $order->get_meta('_sdmc_customer_currency');
        
        if (empty($customer_currency) || $customer_currency === $this->base_currency) {
            return $total;
        }
        
        // Calculate order total in customer currency
        $order_total_currency = 0;
        
        // Add item totals
        foreach ($order->get_items() as $item) {
            $original_price = $item->get_meta('_sdmc_original_price');
            if (!empty($original_price)) {
                $order_total_currency += (float)$original_price * $item->get_quantity();
            }
        }
        
        // Add fees
        foreach ($order->get_fees() as $fee) {
            $fee_total = $fee->get_meta('_sdmc_fee_total');
            if (!empty($fee_total)) {
                $order_total_currency += (float)$fee_total;
            } else {
                // Fallback: convert from ZAR
                $fee_total = $fee->get_total();
                if ($fee_total > 0) {
                    $order_total_currency += $this->convert_amount_to_display_currency($fee_total);
                }
            }
        }
        
        // Add shipping
        $shipping_total = $order->get_meta('_sdmc_shipping_total');
        if (!empty($shipping_total)) {
            $order_total_currency += (float)$shipping_total;
        }
        
        // Subtract coupon discounts
        $discount_total = $order->get_meta('_sdmc_discount_total');
        if (!empty($discount_total)) {
            $order_total_currency -= (float)$discount_total;
        }
        
        // Subtract other discounts
        foreach ($order->get_coupons() as $coupon) {
            $coupon_discount = $coupon->get_meta('_sdmc_discount_amount');
            if (!empty($coupon_discount)) {
                $order_total_currency -= (float)$coupon_discount;
            }
        }
        
        if ($order_total_currency > 0) {
            $symbol = SDMC_Currency::get_symbol($customer_currency);
            return '<strong>' . $symbol . number_format($order_total_currency, 2) . '</strong>';
        }
        
        return $total;
    }
    
    /**
     * Filter order discount total for display
     * Shows discount in customer's currency on thank you page and emails
     * 
     * @param float $discount
     * @param WC_Order $order
     * @return float
     */
    public function filter_order_discount_total($discount, $order) {
        // Only filter on frontend
        if (is_admin() && !wp_doing_ajax()) {
            return $discount;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $discount;
        }
        
        $customer_currency = $order->get_meta('_sdmc_customer_currency');
        
        if (empty($customer_currency) || $customer_currency === $this->base_currency) {
            return $discount;
        }
        
        // Get the stored discount in customer's currency
        $stored_discount = $order->get_meta('_sdmc_discount_total');
        
        if (!empty($stored_discount)) {
            return (float)$stored_discount;
        }
        
        return $discount;
    }
    
    /**
     * Filter coupon discount HTML in order details
     * Shows coupon discount in customer's currency
     * 
     * @param string $html
     * @param WC_Coupon $coupon
     * @return string
     */
    public function filter_order_coupon_discount_html($html, $coupon) {
        // Only filter on frontend
        if (is_admin() && !wp_doing_ajax()) {
            return $html;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $html;
        }
        
        // Try to get the order from the global
        global $theorder;
        if (!$theorder) {
            return $html;
        }
        
        $customer_currency = $theorder->get_meta('_sdmc_customer_currency');
        
        if (empty($customer_currency) || $customer_currency === $this->base_currency) {
            return $html;
        }
        
        // Get the stored coupon discount in customer's currency
        $coupon_code = $coupon->get_code();
        $stored_discount = $theorder->get_meta('_sdmc_coupon_' . $coupon_code);
        
        if (!empty($stored_discount)) {
            $symbol = SDMC_Currency::get_symbol($customer_currency);
            return '-' . $symbol . number_format((float)$stored_discount, 2);
        }
        
        return $html;
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
                
                // Get the converted price (currency-specific, from USD, or from ZAR)
                $converted_price = $this->get_converted_price($product_id, $customer_currency);
                
                if ($converted_price !== false) {
                    $item->update_meta_data('_sdmc_original_currency', $customer_currency);
                    $item->update_meta_data('_sdmc_original_price', $converted_price);
                    
                    // Track the source of the price for display in admin
                    $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($customer_currency), true);
                    $usd_price = get_post_meta($product_id, '_sd_price_usd', true);
                    
                    if (!empty($currency_price) && is_numeric($currency_price)) {
                        // Direct currency-specific price
                        $item->update_meta_data('_sdmc_price_source', 'direct');
                        $item->update_meta_data('_sdmc_is_converted', '0');
                    } elseif (!empty($usd_price) && is_numeric($usd_price) && $customer_currency !== 'USD') {
                        // Converted from USD
                        $item->update_meta_data('_sdmc_price_source', 'usd');
                        $item->update_meta_data('_sdmc_is_converted', '1');
                        $item->update_meta_data('_sdmc_source_price', (float)$usd_price);
                    } else {
                        // Converted from ZAR
                        $item->update_meta_data('_sdmc_price_source', 'zar');
                        $item->update_meta_data('_sdmc_is_converted', '1');
                    }
                }
            }
            
            // Store coupon/discount totals in customer's currency
            if (function_exists('WC') && isset(WC()->cart)) {
                $discount_total_zar = WC()->cart->get_discount_total();
                if ($discount_total_zar > 0) {
                    $discount_total_currency = $this->convert_amount_to_display_currency($discount_total_zar);
                    $order->update_meta_data('_sdmc_discount_total', $discount_total_currency);
                }
                
                // Store individual coupon discounts
                foreach (WC()->cart->get_coupons() as $coupon_code => $coupon) {
                    $coupon_discount_zar = WC()->cart->get_coupon_discount_amount($coupon_code, WC()->cart->display_cart_ex_tax);
                    if ($coupon_discount_zar > 0) {
                        $coupon_discount_currency = $this->convert_amount_to_display_currency($coupon_discount_zar);
                        $order->update_meta_data('_sdmc_coupon_' . $coupon_code, $coupon_discount_currency);
                    }
                }
                
                // Store shipping total in customer's currency
                $shipping_total_zar = WC()->cart->get_shipping_total();
                if ($shipping_total_zar > 0) {
                    $shipping_total_currency = $this->convert_amount_to_display_currency($shipping_total_zar);
                    $order->update_meta_data('_sdmc_shipping_total', $shipping_total_currency);
                }
            }
        }
    }
    
    /**
     * Display checkout notice
     * Shows that payment will be in ZAR but keeps the selected currency visible
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
                
                // Get converted price (currency-specific or converted from ZAR)
                $converted_price = $this->get_converted_price($product_id, $customer_currency);
                
                if ($converted_price !== false) {
                    $cart_total_currency += $converted_price * $cart_item['quantity'];
                }
            }
            
            // Subtract discounts
            $discount_total = WC()->cart->get_discount_total();
            if ($discount_total > 0) {
                $cart_total_currency -= $this->convert_amount_to_display_currency($discount_total);
            }
        }
        
        $symbol = SDMC_Currency::get_symbol($customer_currency);
        
        echo '<div class="woocommerce-info sdmc-checkout-currency-notice">';
        if ($cart_total_currency > 0) {
            echo sprintf(
                esc_html__('Your total is %s%s. Your card will be charged in ZAR (South African Rand) at the current exchange rate.%s', 'sd-multicurrency-pro'),
                '<strong>' . $symbol . number_format($cart_total_currency, 2) . '</strong>',
                esc_html($rate_info),
                ''
            );
        } else {
            echo sprintf(
                esc_html__('Payment will be processed in ZAR (South African Rand) via Yoco.', 'sd-multicurrency-pro'),
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
                <strong><?php _e('Prices in Customer Currency:', 'sd-multicurrency-pro'); ?></strong>
                <ul style="margin: 5px 0 0 0; padding-left: 20px;">
                    <?php foreach ($order->get_items() as $item) :
                        $original_price = $item->get_meta('_sdmc_original_price');
                        $price_source = $item->get_meta('_sdmc_price_source');
                        $source_price = $item->get_meta('_sdmc_source_price');
                        if (!empty($original_price)) :
                            $symbol = SDMC_Currency::get_symbol($customer_currency);
                    ?>
                    <li>
                        <?php echo esc_html($item->get_name()); ?>: 
                        <?php echo esc_html($symbol . number_format((float)$original_price, 2)); ?> 
                        <?php echo esc_html($customer_currency); ?>
                        <?php 
                        $source_label = '';
                        if ($price_source === 'usd') {
                            $source_label = !empty($source_price) ? 
                                sprintf('(converted from $%s USD)', number_format((float)$source_price, 2)) : 
                                '(converted from USD)';
                        } elseif ($price_source === 'zar') {
                            $source_label = '(auto-converted from ZAR)';
                        }
                        if (!empty($source_label)) : ?>
                            <em style="color: #666; font-size: 11px;"><?php echo esc_html($source_label); ?></em>
                        <?php endif; ?>
                    </li>
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
        $inverse_rate = $order->get_meta('_sdmc_inverse_rate');
        
        // Calculate total in customer's currency
        $total_currency = 0;
        foreach ($order->get_items() as $item) {
            $original_price = $item->get_meta('_sdmc_original_price');
            if (!empty($original_price)) {
                $total_currency += (float)$original_price * $item->get_quantity();
            }
        }
        
        // Subtract discount
        $discount_total = $order->get_meta('_sdmc_discount_total');
        if (!empty($discount_total)) {
            $total_currency -= (float)$discount_total;
        }
        
        // Add shipping
        $shipping_total = $order->get_meta('_sdmc_shipping_total');
        if (!empty($shipping_total)) {
            $total_currency += (float)$shipping_total;
        }
        
        $symbol = SDMC_Currency::get_symbol($customer_currency);
        
        if ($plain_text) {
            echo "\n" . __('--- Currency Conversion ---', 'sd-multicurrency-pro') . "\n";
            echo sprintf(__('You paid: %s %s', 'sd-multicurrency-pro'), $symbol . number_format($total_currency, 2), $customer_currency) . "\n";
            if ($inverse_rate) {
                echo sprintf(__('Exchange rate: 1 %s = %s ZAR', 'sd-multicurrency-pro'), $customer_currency, number_format((float)$inverse_rate, 2)) . "\n";
            }
            echo sprintf(__('Amount charged: R%s ZAR', 'sd-multicurrency-pro'), number_format((float)$zar_amount, 2)) . "\n";
            echo "\n";
        } else {
            echo '<div style="margin: 20px 0; padding: 15px; background: #f6f7f7; border-radius: 4px;">';
            echo '<p style="margin: 0;">';
            echo sprintf(
                __('You paid %s %s. Your card was charged in ZAR (South African Rand).', 'sd-multicurrency-pro'),
                '<strong>' . $symbol . number_format($total_currency, 2) . '</strong>',
                esc_html($customer_currency)
            );
            echo '</p>';
            echo '</div>';
        }
    }
}
