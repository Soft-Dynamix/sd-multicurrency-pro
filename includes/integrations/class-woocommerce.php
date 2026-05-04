<?php
/**
 * WooCommerce Integration Class
 * 
 * Handles WooCommerce-specific hooks and filters
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
        // Price filters - only on frontend
        add_filter('woocommerce_product_get_price', [$this, 'custom_price'], 10, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'custom_price'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'custom_price'], 10, 2);
        
        // Variable products
        add_filter('woocommerce_product_variation_get_price', [$this, 'custom_price'], 10, 2);
        add_filter('woocommerce_product_variation_get_regular_price', [$this, 'custom_price'], 10, 2);
        
        // Price HTML
        add_filter('woocommerce_get_price_html', [$this, 'price_html'], 10, 2);
        
        // Currency override
        add_filter('woocommerce_currency', [$this, 'set_currency']);
        
        // Currency symbol
        add_filter('woocommerce_currency_symbol', [$this, 'set_currency_symbol'], 10, 2);
        
        // Checkout - force base currency
        add_action('woocommerce_before_calculate_totals', [$this, 'force_base_currency_checkout']);
    }
    
    /**
     * Custom price filter
     */
    public function custom_price($price, $product) {
        // Don't modify in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }
        
        // Check if Currency class exists
        if (!class_exists('SDMC_Currency')) {
            return $price;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $price;
        }
        
        // Get custom price for currency
        $custom_price = $product->get_meta('_sd_price_' . strtolower($currency));
        
        if (!empty($custom_price) && $custom_price > 0) {
            return (float) $custom_price;
        }
        
        return $price;
    }
    
    /**
     * Price HTML filter
     */
    public function price_html($price_html, $product) {
        // Check if Currency class exists
        if (!class_exists('SDMC_Currency')) {
            return $price_html;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Only modify if not base currency
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get custom price
        $custom_price = $product->get_meta('_sd_price_' . strtolower($currency));
        
        if (empty($custom_price) || $custom_price <= 0) {
            return $price_html;
        }
        
        // Get base price for notice
        $base_price = $product->get_meta('_sd_price_' . strtolower($this->base_currency));
        if (empty($base_price)) {
            $base_price = $product->get_price();
        }
        
        // Build price HTML
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        $new_html = '<span class="sdmc-price sdmc-price-' . strtolower($currency) . '">';
        $new_html .= '<strong>' . esc_html($symbol . number_format($custom_price, 2)) . '</strong>';
        
        // Add checkout notice
        if (class_exists('SDMC_Settings') && SDMC_Settings::is_checkout_notice_enabled() && $currency !== $this->base_currency) {
            $new_html .= '<br><small class="sdmc-checkout-notice" style="color:#6b7280;font-size:0.85em;">';
            $new_html .= sprintf(esc_html('≈ %s%s charged at checkout'), esc_html($base_symbol), esc_html(number_format($base_price, 2)));
            $new_html .= '</small>';
        }
        
        $new_html .= '</span>';
        
        return $new_html;
    }
    
    /**
     * Set WooCommerce currency
     */
    public function set_currency($currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $currency;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $currency;
        }
        
        return SDMC_Currency::get_currency();
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
        
        return SDMC_Currency::get_symbol($currency);
    }
    
    /**
     * Force base currency at checkout
     */
    public function force_base_currency_checkout($cart) {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return;
        }
        
        // Skip if already base currency
        $current_currency = SDMC_Currency::get_currency();
        if ($current_currency === $this->base_currency) {
            return;
        }
        
        // Force currency cookie to base
        SDMC_Currency::set_currency($this->base_currency);
        
        // Set cart prices to base currency
        if (is_object($cart) && method_exists($cart, 'get_cart')) {
            foreach ($cart->get_cart() as $cart_item) {
                if (!isset($cart_item['data'])) {
                    continue;
                }
                
                $product = $cart_item['data'];
                
                // Get base currency price
                $base_price = $product->get_meta('_sd_price_' . strtolower($this->base_currency));
                
                if (!empty($base_price) && $base_price > 0) {
                    $product->set_price((float) $base_price);
                }
            }
        }
    }
}
