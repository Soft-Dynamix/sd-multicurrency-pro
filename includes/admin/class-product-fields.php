<?php
/**
 * Admin Product Fields Class
 * 
 * Adds multi-currency pricing fields to WooCommerce products
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Admin_ProductFields {
    
    /**
     * Instance
     */
    private static $instance = null;
    
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
        // Add fields to WooCommerce product pricing section
        add_action('woocommerce_product_options_pricing', [$this, 'add_currency_price_fields']);
        
        // Save the fields - use multiple hooks for compatibility
        add_action('woocommerce_process_product_meta', [$this, 'save_currency_price_fields'], 10, 2);
        add_action('woocommerce_process_product_meta_simple', [$this, 'save_currency_price_fields'], 10, 2);
        add_action('save_post_product', [$this, 'save_on_product_save'], 10, 2);
        
        // Add CSS for the fields
        add_action('admin_head', [$this, 'admin_css']);
    }
    
    /**
     * Add currency price fields to product pricing section
     */
    public function add_currency_price_fields() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $settings = get_option('sdmc_settings', []);
        $currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
        $base_currency = $settings['base_currency'] ?? 'ZAR';
        
        echo '<div class="options_group sdmc-currency-prices show_if_simple show_if_variable">';
        echo '<p class="form-field" style="padding: 10px 20px; background: #f6f7f7; margin: 0;">';
        echo '<strong>' . esc_html__('Multi-Currency Prices', 'sd-multicurrency-pro') . '</strong>';
        echo '<span class="description" style="display: block; margin-top: 5px; font-weight: normal;">';
        echo esc_html__('Enter prices for each currency. Used for display and converted to ZAR at checkout for Yoco.', 'sd-multicurrency-pro');
        echo '</span>';
        echo '</p>';
        
        foreach ($currencies as $currency) {
            $field_name = '_sd_price_' . strtolower($currency);
            $value = get_post_meta($post->ID, $field_name, true);
            $symbol = class_exists('SDMC_Currency') ? SDMC_Currency::get_symbol($currency) : $currency;
            $is_base = ($currency === $base_currency);
            
            woocommerce_wp_text_input([
                'id' => $field_name,
                'label' => sprintf(__('Price in %s (%s)', 'sd-multicurrency-pro'), $currency, $symbol),
                'placeholder' => sprintf(__('Enter price in %s', 'sd-multicurrency-pro'), $currency),
                'desc_tip' => true,
                'description' => $is_base 
                    ? sprintf(__('Base currency price for %s payment processing.', 'sd-multicurrency-pro'), $base_currency)
                    : sprintf(__('Price in %s. Converted to %s at checkout.', 'sd-multicurrency-pro'), $currency, $base_currency),
                'data_type' => 'price',
                'value' => $value,
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0'
                ]
            ]);
        }
        
        echo '</div>';
    }
    
    /**
     * Save currency price fields - WooCommerce hook
     */
    public function save_currency_price_fields($post_id, $post) {
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $settings = get_option('sdmc_settings', []);
        $currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
        
        foreach ($currencies as $currency) {
            $field_name = '_sd_price_' . strtolower($currency);
            
            if (isset($_POST[$field_name])) {
                $price = wc_format_decimal($_POST[$field_name]);
                
                if (!empty($price) && is_numeric($price)) {
                    update_post_meta($post_id, $field_name, floatval($price));
                } else {
                    delete_post_meta($post_id, $field_name);
                }
            }
        }
    }
    
    /**
     * Save on product save - fallback hook
     */
    public function save_on_product_save($post_id, $post) {
        // Only run for products
        if ($post->post_type !== 'product') {
            return;
        }
        
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $settings = get_option('sdmc_settings', []);
        $currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
        
        foreach ($currencies as $currency) {
            $field_name = '_sd_price_' . strtolower($currency);
            
            if (isset($_POST[$field_name])) {
                $price = wc_format_decimal($_POST[$field_name]);
                
                if (!empty($price) && is_numeric($price)) {
                    update_post_meta($post_id, $field_name, floatval($price));
                }
            }
        }
    }
    
    /**
     * Admin CSS for styling the fields
     */
    public function admin_css() {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'product') {
            echo '<style>
                .sdmc-currency-prices {
                    background: #fff;
                    border: 1px solid #e5e5e5;
                    margin: 10px 0;
                }
                .sdmc-currency-prices .form-field {
                    margin: 10px 20px !important;
                }
            </style>';
        }
    }
}
