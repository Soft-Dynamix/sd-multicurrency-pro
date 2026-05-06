<?php
/**
 * Frontend Display Class
 * 
 * Handles frontend price display modifications for Tutor LMS
 * WooCommerce handling is in class-woocommerce.php
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
        
        // Tutor LMS - Direct price replacement via output buffer
        add_action('template_redirect', [$this, 'start_global_price_buffer'], 1);
        
        // Add checkout notice
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 5);
        
        // Note: WooCommerce price filters are handled in class-woocommerce.php
        // to avoid duplicate processing
    }
    
    /**
     * Start global price buffer for Tutor LMS
     */
    public function start_global_price_buffer() {
        if (is_admin()) {
            return;
        }
        
        ob_start([$this, 'replace_all_prices']);
    }
    
    /**
     * Replace all prices in the output (for Tutor LMS and other non-WooCommerce content)
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
}
