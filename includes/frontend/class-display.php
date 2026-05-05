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
        
        // WooCommerce currency filters
        add_filter('woocommerce_currency', [$this, 'change_woocommerce_currency'], 99);
        add_filter('woocommerce_currency_symbol', [$this, 'change_currency_symbol'], 99, 2);
        
        // Tutor LMS - Direct price replacement via output buffer
        add_action('template_redirect', [$this, 'start_global_price_buffer'], 1);
        
        // Add checkout notice
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 5);
        
        // Add currency info to order emails
        add_action('woocommerce_email_after_order_table', [$this, 'email_currency_info'], 10, 4);
    }
    
    /**
     * Start global price buffer
     */
    public function start_global_price_buffer() {
        if (is_admin()) {
            return;
        }
        
        ob_start([$this, 'replace_all_prices']);
    }
    
    /**
     * Replace all prices in the output
     */
    public function replace_all_prices($content) {
        if (!class_exists('SDMC_Currency')) {
            return $content;
        }
        
        $currency = $this->get_current_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $content;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        // Get current course/product ID
        $id = get_the_ID();
        
        if ($id) {
            // Try to get custom price
            $custom_price = get_post_meta($id, '_sd_price_' . strtolower($currency), true);
            
            if (!empty($custom_price) && is_numeric($custom_price)) {
                // We have a custom price - replace both symbol AND amount
                // Pattern: base symbol followed by number (with optional decimals and commas)
                $pattern = '/' . preg_quote($base_symbol, '/') . '\s*[\d,]+\.?\d*/';
                $replacement = $symbol . ' ' . number_format((float)$custom_price, 2);
                $content = preg_replace($pattern, $replacement, $content);
            }
        }
        
        return $content;
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
        if (is_object($product) && method_exists($product, 'is_type') && $product->is_type('variation')) {
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
