<?php
/**
 * Settings Class
 * 
 * Handles plugin settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Settings {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Option name
     */
    const OPTION_NAME = 'sdmc_settings';
    
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
        // Handle AJAX settings save
        add_action('wp_ajax_sdmc_save_settings', [$this, 'ajax_save_settings']);
    }
    
    /**
     * Get all settings
     */
    public static function get_settings() {
        return get_option(self::OPTION_NAME, [
            'base_currency' => 'ZAR',
            'active_currencies' => ['ZAR', 'USD', 'GBP', 'EUR'],
            'symbol_map' => [
                'ZAR' => 'R',
                'USD' => '$',
                'GBP' => '£',
                'EUR' => '€'
            ],
            'checkout_notice' => 1,
            'pricing_mode' => 'manual',
            'show_flag' => 1,
            'switcher_style' => 'dropdown'
        ]);
    }
    
    /**
     * Get a specific setting
     */
    public static function get($key, $default = null) {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update settings
     */
    public static function update_settings($settings) {
        update_option(self::OPTION_NAME, $settings);
    }
    
    /**
     * Get active currencies
     */
    public static function get_active_currencies() {
        return self::get('active_currencies', ['ZAR', 'USD', 'GBP', 'EUR']);
    }
    
    /**
     * Get base currency
     */
    public static function get_base_currency() {
        return self::get('base_currency', 'ZAR');
    }
    
    /**
     * Is checkout notice enabled
     */
    public static function is_checkout_notice_enabled() {
        return (bool) self::get('checkout_notice', 1);
    }
    
    /**
     * Get switcher style
     */
    public static function get_switcher_style() {
        return self::get('switcher_style', 'dropdown');
    }
    
    /**
     * AJAX save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : [];
        
        // Sanitize settings
        $sanitized = [
            'base_currency' => sanitize_text_field($settings['base_currency'] ?? 'ZAR'),
            'active_currencies' => array_map('sanitize_text_field', $settings['active_currencies'] ?? ['ZAR']),
            'symbol_map' => [],
            'checkout_notice' => isset($settings['checkout_notice']) ? 1 : 0,
            'pricing_mode' => sanitize_text_field($settings['pricing_mode'] ?? 'manual'),
            'show_flag' => isset($settings['show_flag']) ? 1 : 0,
            'switcher_style' => sanitize_text_field($settings['switcher_style'] ?? 'dropdown')
        ];
        
        // Sanitize symbol map
        if (!empty($settings['symbol_map']) && is_array($settings['symbol_map'])) {
            foreach ($settings['symbol_map'] as $currency => $symbol) {
                $sanitized['symbol_map'][sanitize_text_field($currency)] = sanitize_text_field($symbol);
            }
        }
        
        self::update_settings($sanitized);
        
        wp_send_json_success(['message' => 'Settings saved successfully']);
    }
}
