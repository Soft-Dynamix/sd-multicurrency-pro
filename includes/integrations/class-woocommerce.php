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
        // Currency symbol filter - change symbol based on selected currency
        add_filter('woocommerce_currency_symbol', [$this, 'set_currency_symbol'], 10, 2);
        
        // Checkout - force base currency for payment gateways
        add_action('woocommerce_before_calculate_totals', [$this, 'force_base_currency_checkout']);
        
        // Reset to display currency after checkout calculation
        add_action('woocommerce_after_calculate_totals', [$this, 'restore_display_currency']);
        
        // Add meta box for currency prices on products
        add_action('woocommerce_product_options_pricing', [$this, 'add_currency_price_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_currency_price_fields'], 10, 2);
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
     * Force base currency at checkout for payment processing
     */
    public function force_base_currency_checkout($cart) {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Only run at checkout
        if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
            return;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return;
        }
        
        // Get current display currency
        $current_currency = SDMC_Currency::get_currency();
        
        // Skip if already base currency
        if ($current_currency === $this->base_currency) {
            return;
        }
        
        // Set cart prices to base currency for payment
        if (is_object($cart) && method_exists($cart, 'get_cart')) {
            foreach ($cart->get_cart() as $cart_item) {
                if (!isset($cart_item['data'])) {
                    continue;
                }
                
                $product = $cart_item['data'];
                $product_id = $product->get_id();
                
                // Get base currency price from meta
                $base_price = get_post_meta($product_id, '_sd_price_' . strtolower($this->base_currency), true);
                
                // Fallback to regular price if no base price set
                if (empty($base_price) || !is_numeric($base_price)) {
                    $base_price = $product->get_regular_price();
                }
                
                if (!empty($base_price) && $base_price > 0) {
                    $product->set_price((float) $base_price);
                }
            }
        }
    }
    
    /**
     * Restore display currency after checkout calculation
     */
    public function restore_display_currency() {
        // Symbol filter will handle display
    }
    
    /**
     * Add currency price fields to product edit page
     */
    public function add_currency_price_fields() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $currencies = ['ZAR', 'USD', 'GBP', 'EUR']; // All currencies including base
        
        echo '<div class="options_group sdmc-currency-prices">';
        echo '<p class="form-field" style="padding: 10px 20px; background: #f6f7f7; margin: 0;">';
        echo '<strong>' . esc_html__('Multi-Currency Prices', 'sd-multicurrency-pro') . '</strong>';
        echo '</p>';
        
        foreach ($currencies as $currency) {
            $field_name = '_sd_price_' . strtolower($currency);
            $value = get_post_meta($post->ID, $field_name, true);
            $symbol = SDMC_Currency::get_symbol($currency);
            
            woocommerce_wp_text_input([
                'id' => $field_name,
                'label' => sprintf(__('Price in %s (%s)', 'sd-multicurrency-pro'), $currency, $symbol),
                'placeholder' => sprintf(__('Enter price in %s', 'sd-multicurrency-pro'), $currency),
                'desc_tip' => true,
                'description' => sprintf(__('Enter the fixed price for this product in %s. Leave empty to use base price.', 'sd-multicurrency-pro'), $currency),
                'data_type' => 'price',
                'value' => $value,
            ]);
        }
        
        echo '</div>';
    }
    
    /**
     * Save currency price fields
     */
    public function save_currency_price_fields($post_id, $post) {
        $currencies = ['ZAR', 'USD', 'GBP', 'EUR'];
        
        foreach ($currencies as $currency) {
            $field_name = '_sd_price_' . strtolower($currency);
            
            if (isset($_POST[$field_name])) {
                $value = wc_format_decimal($_POST[$field_name]);
                update_post_meta($post_id, $field_name, $value);
            }
        }
    }
}
