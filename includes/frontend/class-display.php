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
        // Add checkout notice
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 5);
        
        // Add currency info to order emails
        add_action('woocommerce_email_after_order_table', [$this, 'email_currency_info'], 10, 4);
    }
    
    /**
     * Display checkout notice
     */
    public function checkout_notice() {
        $settings = SDMC_Settings::get_settings();
        
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
        $settings = SDMC_Settings::get_settings();
        $base_currency = $settings['base_currency'] ?? 'ZAR';
        $symbol = SDMC_Currency::get_symbol($base_currency);
        
        if ($plain_text) {
            echo "\n" . sprintf(__('Payment processed in %s (%s)', 'sd-multicurrency-pro'), $base_currency, $symbol) . "\n";
        } else {
            echo '<p><small>' . sprintf(__('Payment processed in %s (%s)', 'sd-multicurrency-pro'), esc_html($base_currency), esc_html($symbol)) . '</small></p>';
        }
    }
}
