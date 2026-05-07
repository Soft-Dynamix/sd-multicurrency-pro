<?php
/**
 * Currency Class
 * 
 * Handles currency detection, storage, and formatting
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Currency {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Cookie name
     */
    const COOKIE_NAME = 'sdmc_currency';
    
    /**
     * Default currency
     */
    const DEFAULT_CURRENCY = 'ZAR';
    
    /**
     * Currency symbols
     */
    private static $symbols = [
        'ZAR' => 'R',
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'NZD' => 'NZ$',
    ];
    
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
        // Handle currency switching via AJAX
        add_action('wp_ajax_sdmc_switch_currency', [$this, 'ajax_switch_currency']);
        add_action('wp_ajax_nopriv_sdmc_switch_currency', [$this, 'ajax_switch_currency']);
        
        // Handle currency reset via AJAX
        add_action('wp_ajax_sdmc_reset_currency', [$this, 'ajax_reset_currency']);
        add_action('wp_ajax_nopriv_sdmc_reset_currency', [$this, 'ajax_reset_currency']);
    }
    
    /**
     * Get current currency
     */
    public static function get_currency() {
        // Check cookie
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $currency = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
            if (self::is_valid_currency($currency)) {
                return $currency;
            }
        }
        
        // Check settings for default
        $settings = get_option('sdmc_settings', []);
        if (!empty($settings['base_currency'])) {
            return $settings['base_currency'];
        }
        
        return self::DEFAULT_CURRENCY;
    }
    
    /**
     * Set currency
     */
    public static function set_currency($currency) {
        if (!self::is_valid_currency($currency)) {
            return false;
        }
        
        // Set cookie for 30 days
        setcookie(self::COOKIE_NAME, $currency, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::COOKIE_NAME] = $currency;
        
        return true;
    }
    
    /**
     * Reset currency (clear cookie and re-detect based on geolocation)
     */
    public static function reset_currency() {
        // Clear the currency cookie
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            unset($_COOKIE[self::COOKIE_NAME]);
        }
        setcookie(self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        // Also clear the detected country cookie to force fresh detection
        if (class_exists('SDMC_Geolocation')) {
            if (isset($_COOKIE[SDMC_Geolocation::COUNTRY_COOKIE])) {
                unset($_COOKIE[SDMC_Geolocation::COUNTRY_COOKIE]);
            }
            setcookie(SDMC_Geolocation::COUNTRY_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        
        return true;
    }
    
    /**
     * Check if currency is valid
     */
    public static function is_valid_currency($currency) {
        $settings = get_option('sdmc_settings', []);
        $active_currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
        
        return in_array($currency, $active_currencies);
    }
    
    /**
     * Get currency symbol
     */
    public static function get_symbol($currency) {
        // Check custom symbols in settings
        $settings = get_option('sdmc_settings', []);
        if (!empty($settings['symbol_map'][$currency])) {
            return $settings['symbol_map'][$currency];
        }
        
        // Check default symbols
        if (isset(self::$symbols[$currency])) {
            return self::$symbols[$currency];
        }
        
        // Return currency code as fallback
        return $currency;
    }
    
    /**
     * Format price
     */
    public static function format_price($amount, $currency = null) {
        if ($currency === null) {
            $currency = self::get_currency();
        }
        
        $symbol = self::get_symbol($currency);
        
        return $symbol . number_format($amount, 2);
    }
    
    /**
     * Get all active currencies
     */
    public static function get_active_currencies() {
        $settings = get_option('sdmc_settings', []);
        return $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
    }
    
    /**
     * AJAX switch currency
     */
    public function ajax_switch_currency() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        $currency = sanitize_text_field($_POST['currency'] ?? '');
        
        if (empty($currency)) {
            wp_send_json_error(['message' => 'Currency is required']);
        }
        
        if (!self::is_valid_currency($currency)) {
            wp_send_json_error(['message' => 'Invalid currency']);
        }
        
        self::set_currency($currency);
        
        wp_send_json_success([
            'currency' => $currency,
            'symbol' => self::get_symbol($currency)
        ]);
    }
    
    /**
     * AJAX reset currency and re-detect based on geolocation
     */
    public function ajax_reset_currency() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        // Clear cookies
        self::reset_currency();
        
        // Re-detect based on geolocation
        $detected_currency = self::DEFAULT_CURRENCY;
        $detected_country = null;
        
        if (class_exists('SDMC_Geolocation')) {
            $country = SDMC_Geolocation::get_country();
            if ($country) {
                $detected_country = $country;
                $currency = SDMC_Geolocation::get_currency_for_country($country);
                if ($currency && self::is_valid_currency($currency)) {
                    $detected_currency = $currency;
                    self::set_currency($currency);
                }
            }
        }
        
        wp_send_json_success([
            'currency' => $detected_currency,
            'symbol' => self::get_symbol($detected_currency),
            'country' => $detected_country,
            'message' => $detected_country 
                ? sprintf('Detected location: %s. Currency set to %s', $detected_country, $detected_currency)
                : 'Could not detect location. Using default currency.'
        ]);
    }
}
