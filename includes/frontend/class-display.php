<?php
/**
 * Frontend Display Class
 * 
 * Handles frontend price display modifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Frontend_Display {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Current currency cache
     */
    private $current_currency = null;
    
    /**
     * Base currency
     */
    private $base_currency = 'ZAR';
    
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
        $settings = get_option('sdmc_settings', []);
        $this->base_currency = $settings['base_currency'] ?? 'ZAR';
        
        // WooCommerce price filters
        add_filter('woocommerce_product_get_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'get_product_price'], 99, 2);
        
        // Variation prices
        add_filter('woocommerce_product_variation_get_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_sale_price', [$this, 'get_product_price'], 99, 2);
        
        // Price HTML filters
        add_filter('woocommerce_get_price_html', [$this, 'format_price_html'], 99, 2);
        
        // Cart item prices
        add_filter('woocommerce_cart_item_price', [$this, 'cart_item_price'], 99, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'cart_item_subtotal'], 99, 3);
        
        // WooCommerce currency filters
        add_filter('woocommerce_currency', [$this, 'change_woocommerce_currency'], 99);
        add_filter('woocommerce_currency_symbol', [$this, 'change_currency_symbol'], 99, 2);
        
        // Tutor LMS - Multiple approaches
        add_filter('tutor_course_price', [$this, 'tutor_course_price'], 999, 2);
        add_filter('get_tutor_course_price', [$this, 'tutor_course_price'], 999, 2);
        add_filter('tutor/course/price', [$this, 'tutor_course_price'], 999, 2);
        
        // Tutor LMS - Filter the actual price meta
        add_filter('get_post_metadata', [$this, 'filter_tutor_price_meta'], 999, 4);
        
        // Tutor LMS - Output buffering for price sections
        add_action('tutor_course/single/content/before', [$this, 'start_tutor_price_buffer'], 1);
        add_action('tutor_course/single/content/after', [$this, 'end_tutor_price_buffer'], 999);
        
        // Tutor LMS - Loop price buffer
        add_action('tutor_course/before/loop', [$this, 'start_tutor_price_buffer'], 1);
        add_action('tutor_course/after/loop', [$this, 'end_tutor_price_buffer'], 999);
        
        // Tutor LMS - Archive page buffer
        add_action('tutor_before_course_archive_loop', [$this, 'start_tutor_price_buffer'], 1);
        add_action('tutor_after_course_archive_loop', [$this, 'end_tutor_price_buffer'], 999);
        
        // Add checkout notice
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 5);
        
        // Add currency info to order emails
        add_action('woocommerce_email_after_order_table', [$this, 'email_currency_info'], 10, 4);
    }
    
    /**
     * Get current currency (cached)
     */
    private function get_current_currency() {
        if ($this->current_currency === null) {
            $this->current_currency = SDMC_Currency::get_currency();
        }
        return $this->current_currency;
    }
    
    /**
     * Change WooCommerce currency
     */
    public function change_woocommerce_currency($currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $currency;
        }
        
        return $this->get_current_currency();
    }
    
    /**
     * Change WooCommerce currency symbol
     */
    public function change_currency_symbol($symbol, $currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $symbol;
        }
        
        return SDMC_Currency::get_symbol($currency);
    }
    
    /**
     * Get product price in current currency
     */
    public function get_product_price($price, $product) {
        // Skip if in admin or no product
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }
        
        // Skip for base currency
        $currency = $this->get_current_currency();
        
        if ($currency === $this->base_currency) {
            return $price;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        
        // Check for variation parent
        if ($product->is_type('variation')) {
            $product_id = $product->get_parent_id();
        }
        
        // Get currency-specific price
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($currency), true);
        
        // If no price set, return original (fallback to base)
        if (empty($currency_price) || !is_numeric($currency_price)) {
            return $price;
        }
        
        return wc_format_decimal($currency_price);
    }
    
    /**
     * Format price HTML with currency symbol
     */
    public function format_price_html($price_html, $product) {
        // Skip if in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        $currency = $this->get_current_currency();
        
        // Only modify if not base currency
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get the symbol for current currency
        $symbol = SDMC_Currency::get_symbol($currency);
        
        // Replace the symbol in price HTML
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        $price_html = str_replace($base_symbol, $symbol, $price_html);
        
        return $price_html;
    }
    
    /**
     * Filter cart item price
     */
    public function cart_item_price($price, $cart_item, $cart_item_key) {
        $currency = $this->get_current_currency();
        
        if ($currency === $this->base_currency) {
            return $price;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        return str_replace($base_symbol, $symbol, $price);
    }
    
    /**
     * Filter cart item subtotal
     */
    public function cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        $currency = $this->get_current_currency();
        
        if ($currency === $this->base_currency) {
            return $subtotal;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        return str_replace($base_symbol, $symbol, $subtotal);
    }
    
    /**
     * Filter Tutor LMS course price
     */
    public function tutor_course_price($price_html, $course_id = null) {
        // Skip if in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        if (!$course_id) {
            $course_id = get_the_ID();
        }
        
        if (!$course_id) {
            return $price_html;
        }
        
        $currency = $this->get_current_currency();
        
        // If base currency, return as-is
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get currency-specific price
        $currency_price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
        
        if (empty($currency_price) || !is_numeric($currency_price)) {
            return $price_html;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        
        return '<span class="sdmc-price">' . esc_html($symbol . number_format((float)$currency_price, 2)) . '</span>';
    }
    
    /**
     * Filter Tutor LMS price meta directly
     */
    public function filter_tutor_price_meta($metadata, $object_id, $meta_key, $single) {
        // Only filter specific Tutor price meta keys
        if (!in_array($meta_key, ['_tutor_course_price_type', '_tutor_regular_price', '_tutor_sale_price'])) {
            return $metadata;
        }
        
        // Skip in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $metadata;
        }
        
        $currency = $this->get_current_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $metadata;
        }
        
        // Get our custom price
        $custom_price = get_post_meta($object_id, '_sd_price_' . strtolower($currency), true);
        
        if (!empty($custom_price) && is_numeric($custom_price)) {
            return $custom_price;
        }
        
        return $metadata;
    }
    
    /**
     * Start Tutor LMS price buffer
     */
    public function start_tutor_price_buffer() {
        ob_start([$this, 'replace_prices_in_content']);
    }
    
    /**
     * End Tutor LMS price buffer
     */
    public function end_tutor_price_buffer() {
        ob_end_flush();
    }
    
    /**
     * Replace prices in content
     */
    public function replace_prices_in_content($content) {
        $currency = $this->get_current_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $content;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        // Replace R symbol with current currency symbol
        // Pattern: R followed by number (with optional decimals and thousands separator)
        $content = preg_replace(
            '/\bR\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/',
            $symbol . ' $1',
            $content
        );
        
        return $content;
    }
    
    /**
     * Display checkout notice
     */
    public function checkout_notice() {
        $settings = get_option('sdmc_settings', []);
        
        if (empty($settings['checkout_notice'])) {
            return;
        }
        
        $base_currency = $settings['base_currency'] ?? 'ZAR';
        $symbol = SDMC_Currency::get_symbol($base_currency);
        
        echo '<div class="sdmc-checkout-notice woocommerce-info">';
        echo sprintf(
            esc_html__('All transactions are processed in %s (%s). Currency conversion is for display purposes only.', 'sd-multicurrency-pro'),
            esc_html($base_currency),
            esc_html($symbol)
        );
        echo '</div>';
    }
    
    /**
     * Add currency info to emails
     */
    public function email_currency_info($order, $sent_to_admin, $plain_text, $email) {
        $settings = get_option('sdmc_settings', []);
        $base_currency = $settings['base_currency'] ?? 'ZAR';
        $symbol = SDMC_Currency::get_symbol($base_currency);
        
        if ($plain_text) {
            echo "\n" . sprintf(__('Payment processed in %s (%s)', 'sd-multicurrency-pro'), $base_currency, $symbol) . "\n";
        } else {
            echo '<p><small>' . sprintf(__('Payment processed in %s (%s)', 'sd-multicurrency-pro'), esc_html($base_currency), esc_html($symbol)) . '</small></p>';
        }
    }
}
