<?php
/**
 * Admin Product Fields Class
 * 
 * Adds multi-currency pricing fields to products
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
        // Add meta box
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        
        // Save meta
        add_action('save_post_product', [$this, 'save_meta'], 10, 2);
    }
    
    /**
     * Add meta box
     */
    public function add_meta_box() {
        add_meta_box(
            'sdmc_product_pricing',
            'Multi-Currency Pricing',
            [$this, 'render_meta_box'],
            'product',
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('sdmc_product_pricing', 'sdmc_product_pricing_nonce');
        
        $currencies = SDMC_Settings::get_active_currencies();
        $base_currency = SDMC_Settings::get_base_currency();
        
        echo '<div class="sdmc-product-pricing">';
        echo '<p class="description">Set prices for each currency. Leave empty to use base price.</p>';
        
        foreach ($currencies as $currency) {
            $symbol = SDMC_Currency::get_symbol($currency);
            $price = get_post_meta($post->ID, '_sd_price_' . strtolower($currency), true);
            $is_base = ($currency === $base_currency);
            
            echo '<p>';
            echo '<label for="sdmc_price_' . esc_attr(strtolower($currency)) . '">';
            echo esc_html($symbol . ' ' . $currency);
            if ($is_base) {
                echo ' <span class="sdmc-base-label">(Base)</span>';
            }
            echo '</label><br>';
            echo '<input type="number" step="0.01" min="0" ';
            echo 'id="sdmc_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'name="sdmc_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'value="' . esc_attr($price) . '" ';
            echo 'placeholder="' . esc_attr($symbol . '0.00') . '" ';
            echo 'style="width:100%">';
            echo '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Save meta
     */
    public function save_meta($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['sdmc_product_pricing_nonce']) || 
            !wp_verify_nonce($_POST['sdmc_product_pricing_nonce'], 'sdmc_product_pricing')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save prices
        $currencies = SDMC_Settings::get_active_currencies();
        
        foreach ($currencies as $currency) {
            $key = 'sdmc_price_' . strtolower($currency);
            
            if (isset($_POST[$key])) {
                $price = sanitize_text_field($_POST[$key]);
                
                if (!empty($price) && is_numeric($price)) {
                    update_post_meta($post_id, '_sd_price_' . strtolower($currency), floatval($price));
                } else {
                    delete_post_meta($post_id, '_sd_price_' . strtolower($currency));
                }
            }
        }
    }
}
