<?php
/**
 * Exchange Rates Class
 * 
 * Handles fetching, storing, and converting currency exchange rates
 * All rates are relative to ZAR (South African Rand) as base currency
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Exchange_Rates {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Transient key for stored rates
     */
    const RATES_TRANSIENT_KEY = 'sdmc_exchange_rates';
    
    /**
     * Option key for manual rate overrides
     */
    const MANUAL_RATES_OPTION = 'sdmc_manual_rates';
    
    /**
     * Option key for last update time
     */
    const LAST_UPDATE_OPTION = 'sdmc_rates_last_update';
    
    /**
     * Cache duration in seconds (1 hour)
     */
    const CACHE_DURATION = 3600;
    
    /**
     * API endpoints (free APIs)
     */
    private static $api_endpoints = [
        'frankfurter' => 'https://api.frankfurter.app/latest?from=ZAR',
        'exchangerate' => 'https://open.er-api.com/v6/latest/ZAR',
    ];
    
    /**
     * Supported currencies with their names
     */
    private static $currency_names = [
        'ZAR' => 'South African Rand',
        'USD' => 'US Dollar',
        'GBP' => 'British Pound',
        'EUR' => 'Euro',
        'AUD' => 'Australian Dollar',
        'CAD' => 'Canadian Dollar',
        'NZD' => 'New Zealand Dollar',
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
        // AJAX handlers
        add_action('wp_ajax_sdmc_refresh_rates', [$this, 'ajax_refresh_rates']);
        add_action('wp_ajax_sdmc_update_manual_rate', [$this, 'ajax_update_manual_rate']);
        add_action('wp_ajax_sdmc_get_rates', [$this, 'ajax_get_rates']);
        
        // Schedule cron for automatic updates
        add_action('sdmc_update_rates_event', [$this, 'scheduled_rate_update']);
        
        // Initialize cron schedule
        add_action('init', [$this, 'setup_cron']);
    }
    
    /**
     * Setup cron schedule
     */
    public function setup_cron() {
        if (!wp_next_scheduled('sdmc_update_rates_event')) {
            wp_schedule_event(time(), 'hourly', 'sdmc_update_rates_event');
        }
    }
    
    /**
     * Get all exchange rates
     * 
     * Returns rates as: 1 ZAR = X units of each currency
     * For conversion TO ZAR: divide foreign amount by rate
     * 
     * @param bool $force_refresh Force refresh from API
     * @return array|false Exchange rates or false on failure
     */
    public static function get_rates($force_refresh = false) {
        // Try to get cached rates first
        if (!$force_refresh) {
            $cached_rates = get_transient(self::RATES_TRANSIENT_KEY);
            if ($cached_rates !== false) {
                return self::apply_manual_overrides($cached_rates);
            }
        }
        
        // Fetch fresh rates
        $rates = self::fetch_rates_from_api();
        
        if ($rates) {
            // Cache the rates
            set_transient(self::RATES_TRANSIENT_KEY, $rates, self::CACHE_DURATION);
            update_option(self::LAST_UPDATE_OPTION, current_time('mysql'));
            
            return self::apply_manual_overrides($rates);
        }
        
        // Return cached rates even if expired (fallback)
        $fallback = get_transient(self::RATES_TRANSIENT_KEY);
        if ($fallback !== false) {
            return self::apply_manual_overrides($fallback);
        }
        
        // Last resort: return default static rates
        return self::get_default_rates();
    }
    
    /**
     * Fetch rates from API
     * 
     * @return array|false
     */
    private static function fetch_rates_from_api() {
        // Try primary API (Frankfurter - reliable, no API key needed)
        $response = wp_remote_get(self::$api_endpoints['frankfurter'], [
            'timeout' => 15,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['rates']) && is_array($body['rates'])) {
                return self::normalize_rates($body['rates']);
            }
        }
        
        // Try backup API
        $response = wp_remote_get(self::$api_endpoints['exchangerate'], [
            'timeout' => 15,
            'sslverify' => false,
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['rates']) && is_array($body['rates'])) {
                return self::normalize_rates($body['rates']);
            }
        }
        
        return false;
    }
    
    /**
     * Normalize rates to consistent format
     * APIs return: 1 ZAR = X units of currency
     * 
     * @param array $api_rates Raw API rates
     * @return array Normalized rates
     */
    private static function normalize_rates($api_rates) {
        $settings = get_option('sdmc_settings', []);
        $active_currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
        
        $normalized = ['ZAR' => 1.0]; // 1 ZAR = 1 ZAR
        
        foreach ($active_currencies as $currency) {
            if ($currency === 'ZAR') continue;
            
            if (isset($api_rates[$currency])) {
                // API returns: 1 ZAR = X currency units
                // So rate is already what we need
                $normalized[$currency] = (float) $api_rates[$currency];
            }
        }
        
        return $normalized;
    }
    
    /**
     * Get default fallback rates (approximate)
     * Used when API fails and no cached rates exist
     * 
     * @return array
     */
    private static function get_default_rates() {
        // Approximate rates: 1 ZAR = X units
        // These are conservative estimates and should be updated ASAP
        return [
            'ZAR' => 1.0,
            'USD' => 0.054,    // ~18.5 ZAR per USD
            'GBP' => 0.043,    // ~23.3 ZAR per GBP
            'EUR' => 0.049,    // ~20.4 ZAR per EUR
            'AUD' => 0.082,    // ~12.2 ZAR per AUD
            'CAD' => 0.074,    // ~13.5 ZAR per CAD
            'NZD' => 0.089,    // ~11.2 ZAR per NZD
        ];
    }
    
    /**
     * Apply manual rate overrides
     * 
     * @param array $rates Original rates
     * @return array Rates with manual overrides applied
     */
    private static function apply_manual_overrides($rates) {
        $manual_rates = get_option(self::MANUAL_RATES_OPTION, []);
        
        if (!empty($manual_rates) && is_array($manual_rates)) {
            foreach ($manual_rates as $currency => $rate) {
                if (!empty($rate) && is_numeric($rate)) {
                    $rates[$currency] = (float) $rate;
                }
            }
        }
        
        return $rates;
    }
    
    /**
     * Convert foreign currency amount to ZAR
     * 
     * @param float $amount Amount in foreign currency
     * @param string $from_currency Source currency code
     * @return float|false Amount in ZAR or false on failure
     */
    public static function convert_to_zar($amount, $from_currency) {
        if ($from_currency === 'ZAR') {
            return (float) $amount;
        }
        
        $rates = self::get_rates();
        
        if (!$rates || !isset($rates[$from_currency])) {
            error_log("SDMC Exchange Rates: No rate found for $from_currency");
            return false;
        }
        
        // Rate represents: 1 ZAR = X units of from_currency
        // So to convert from_currency TO ZAR: amount / rate
        $rate = $rates[$from_currency];
        $zar_amount = (float) $amount / $rate;
        
        return round($zar_amount, 2);
    }
    
    /**
     * Convert ZAR amount to foreign currency
     * 
     * @param float $zar_amount Amount in ZAR
     * @param string $to_currency Target currency code
     * @return float|false Amount in foreign currency or false on failure
     */
    public static function convert_from_zar($zar_amount, $to_currency) {
        if ($to_currency === 'ZAR') {
            return (float) $zar_amount;
        }
        
        $rates = self::get_rates();
        
        if (!$rates || !isset($rates[$to_currency])) {
            return false;
        }
        
        // Rate represents: 1 ZAR = X units of to_currency
        // So to convert ZAR TO to_currency: amount * rate
        $rate = $rates[$to_currency];
        $foreign_amount = (float) $zar_amount * $rate;
        
        return round($foreign_amount, 2);
    }
    
    /**
     * Get rate for a specific currency
     * 
     * @param string $currency Currency code
     * @return float|false Rate or false if not found
     */
    public static function get_rate($currency) {
        $rates = self::get_rates();
        
        if ($rates && isset($rates[$currency])) {
            return $rates[$currency];
        }
        
        return false;
    }
    
    /**
     * Get inverse rate (how many ZAR for 1 unit of currency)
     * 
     * @param string $currency Currency code
     * @return float|false Inverse rate or false
     */
    public static function get_inverse_rate($currency) {
        $rate = self::get_rate($currency);
        
        if ($rate && $rate > 0) {
            return round(1 / $rate, 4);
        }
        
        return false;
    }
    
    /**
     * Get last update time
     * 
     * @return string|false MySQL datetime or false
     */
    public static function get_last_update() {
        return get_option(self::LAST_UPDATE_OPTION, false);
    }
    
    /**
     * Set manual rate override
     * 
     * @param string $currency Currency code
     * @param float|null $rate Rate value or null to remove
     * @return bool
     */
    public static function set_manual_rate($currency, $rate) {
        $manual_rates = get_option(self::MANUAL_RATES_OPTION, []);
        
        if ($rate === null || !is_numeric($rate)) {
            unset($manual_rates[$currency]);
        } else {
            $manual_rates[$currency] = (float) $rate;
        }
        
        return update_option(self::MANUAL_RATES_OPTION, $manual_rates);
    }
    
    /**
     * Get all manual rate overrides
     * 
     * @return array
     */
    public static function get_manual_rates() {
        return get_option(self::MANUAL_RATES_OPTION, []);
    }
    
    /**
     * Get currency name
     * 
     * @param string $currency Currency code
     * @return string
     */
    public static function get_currency_name($currency) {
        return self::$currency_names[$currency] ?? $currency;
    }
    
    /**
     * Get all supported currencies
     * 
     * @return array
     */
    public static function get_supported_currencies() {
        return array_keys(self::$currency_names);
    }
    
    /**
     * Scheduled rate update (cron callback)
     */
    public function scheduled_rate_update() {
        $rates = self::fetch_rates_from_api();
        
        if ($rates) {
            set_transient(self::RATES_TRANSIENT_KEY, $rates, self::CACHE_DURATION);
            update_option(self::LAST_UPDATE_OPTION, current_time('mysql'));
        }
    }
    
    /**
     * AJAX: Refresh rates manually
     */
    public function ajax_refresh_rates() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $rates = self::get_rates(true);
        
        if ($rates) {
            wp_send_json_success([
                'rates' => $rates,
                'last_update' => self::get_last_update(),
                'message' => 'Exchange rates updated successfully'
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to fetch exchange rates']);
        }
    }
    
    /**
     * AJAX: Get current rates
     */
    public function ajax_get_rates() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        $rates = self::get_rates();
        $manual_rates = self::get_manual_rates();
        
        wp_send_json_success([
            'rates' => $rates,
            'manual_rates' => $manual_rates,
            'last_update' => self::get_last_update()
        ]);
    }
    
    /**
     * AJAX: Update manual rate
     */
    public function ajax_update_manual_rate() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $currency = sanitize_text_field($_POST['currency'] ?? '');
        $rate = isset($_POST['rate']) && $_POST['rate'] !== '' ? floatval($_POST['rate']) : null;
        
        if (empty($currency)) {
            wp_send_json_error(['message' => 'Currency is required']);
        }
        
        $result = self::set_manual_rate($currency, $rate);
        
        wp_send_json_success([
            'message' => $rate !== null ? 'Manual rate set' : 'Manual rate removed',
            'rates' => self::get_rates()
        ]);
    }
    
    /**
     * Format rate for display
     * 
     * @param float $rate Rate value
     * @param string $currency Currency code
     * @return string Formatted rate string
     */
    public static function format_rate_display($rate, $currency) {
        $inverse = 1 / $rate;
        return sprintf(
            '1 %s = %.4f ZAR (1 ZAR = %.4f %s)',
            $currency,
            $inverse,
            $rate,
            $currency
        );
    }
}
