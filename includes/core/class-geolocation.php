<?php
/**
 * Geolocation Class
 * 
 * Detects customer location and suggests appropriate currency
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Geolocation {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Cookie name for detected country
     */
    const COUNTRY_COOKIE = 'sdmc_detected_country';
    
    /**
     * Transient key for IP lookup cache
     */
    const IP_CACHE_PREFIX = 'sdmc_ip_';
    
    /**
     * Country to currency mapping
     */
    private static $country_currency_map = [
        // Africa
        'ZA' => 'ZAR',  // South Africa
        'NA' => 'ZAR',  // Namibia (uses ZAR)
        'LS' => 'ZAR',  // Lesotho (uses ZAR)
        'SZ' => 'ZAR',  // Eswatini (uses ZAR)
        
        // Europe
        'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR',
        'NL' => 'EUR', 'BE' => 'EUR', 'AT' => 'EUR', 'PT' => 'EUR',
        'IE' => 'EUR', 'GR' => 'EUR', 'FI' => 'EUR', 'LU' => 'EUR',
        'SI' => 'EUR', 'SK' => 'EUR', 'EE' => 'EUR', 'LV' => 'EUR',
        'LT' => 'EUR', 'CY' => 'EUR', 'MT' => 'EUR',
        'GB' => 'GBP', // United Kingdom
        'CH' => 'EUR', // Switzerland (show EUR, pay in EUR)
        
        // Americas
        'US' => 'USD', // United States
        'CA' => 'CAD', // Canada
        'MX' => 'USD', // Mexico (show USD)
        'BR' => 'USD', // Brazil (show USD)
        'AR' => 'USD', // Argentina (show USD)
        
        // Oceania
        'AU' => 'AUD', // Australia
        'NZ' => 'NZD', // New Zealand
        
        // Asia (show USD as common currency)
        'JP' => 'USD', // Japan
        'CN' => 'USD', // China
        'KR' => 'USD', // South Korea
        'IN' => 'USD', // India
        'SG' => 'USD', // Singapore
        'HK' => 'USD', // Hong Kong
        'AE' => 'USD', // UAE
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
        // Hook into init for early detection
        add_action('init', [$this, 'detect_and_set_currency'], 1);
        
        // AJAX for geolocation
        add_action('wp_ajax_sdmc_get_location', [$this, 'ajax_get_location']);
        add_action('wp_ajax_nopriv_sdmc_get_location', [$this, 'ajax_get_location']);
    }
    
    /**
     * Detect customer location and auto-set currency if not already set
     */
    public function detect_and_set_currency() {
        // Skip in admin
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Skip if customer already has a currency preference
        if (isset($_COOKIE[SDMC_Currency::COOKIE_NAME])) {
            return;
        }
        
        // Skip if geolocation is disabled
        $settings = get_option('sdmc_settings', []);
        if (empty($settings['auto_detect_currency'])) {
            return;
        }
        
        // Get detected country
        $country = $this->get_country();
        
        if ($country && class_exists('SDMC_Currency')) {
            $currency = $this->get_currency_for_country($country);
            $active_currencies = SDMC_Currency::get_active_currencies();
            
            // Try the mapped currency first
            if ($currency && in_array($currency, $active_currencies)) {
                SDMC_Currency::set_currency($currency);
                return;
            }
            
            // Fallback 1: Use USD if the mapped currency is not active
            // This handles cases like Canada -> CAD not active -> use USD
            if (in_array('USD', $active_currencies)) {
                SDMC_Currency::set_currency('USD');
                return;
            }
            
            // Fallback 2: Use the first non-ZAR active currency
            foreach ($active_currencies as $active_currency) {
                if ($active_currency !== 'ZAR') {
                    SDMC_Currency::set_currency($active_currency);
                    return;
                }
            }
            
            // Last resort: ZAR will be used by default (no cookie set)
        }
    }
    
    /**
     * Get customer's country code
     * 
     * @return string|false Country code (e.g., 'US', 'GB', 'ZA')
     */
    public static function get_country() {
        // Check cookie first (cached detection)
        if (isset($_COOKIE[self::COUNTRY_COOKIE])) {
            $country = sanitize_text_field($_COOKIE[self::COUNTRY_COOKIE]);
            if (strlen($country) === 2) {
                return strtoupper($country);
            }
        }
        
        // Try WooCommerce geolocation if available
        if (class_exists('WC_Geolocation')) {
            $ip = self::get_customer_ip();
            $geo = WC_Geolocation::geolocate_ip($ip);
            
            if (!empty($geo['country'])) {
                self::set_country_cookie($geo['country']);
                return $geo['country'];
            }
        }
        
        // Fallback to free IP API
        $country = self::detect_country_by_ip();
        
        if ($country) {
            self::set_country_cookie($country);
        }
        
        return $country;
    }
    
    /**
     * Detect country using external IP API
     * 
     * @return string|false
     */
    private static function detect_country_by_ip() {
        $ip = self::get_customer_ip();
        
        // Skip local IPs
        if (self::is_local_ip($ip)) {
            return false;
        }
        
        // Check cache
        $cached = get_transient(self::IP_CACHE_PREFIX . md5($ip));
        if ($cached) {
            return $cached;
        }
        
        // Try ip-api.com (free, no key, 45 requests/min)
        $response = wp_remote_get('http://ip-api.com/json/' . $ip . '?fields=countryCode', [
            'timeout' => 5,
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!empty($body['countryCode'])) {
                $country = strtoupper($body['countryCode']);
                
                // Cache for 24 hours
                set_transient(self::IP_CACHE_PREFIX . md5($ip), $country, DAY_IN_SECONDS);
                
                return $country;
            }
        }
        
        // Fallback: Try ipapi.co (free tier)
        $response = wp_remote_get('https://ipapi.co/' . $ip . '/country_code/', [
            'timeout' => 5,
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $country = trim(wp_remote_retrieve_body($response));
            
            if (strlen($country) === 2) {
                $country = strtoupper($country);
                
                // Cache for 24 hours
                set_transient(self::IP_CACHE_PREFIX . md5($ip), $country, DAY_IN_SECONDS);
                
                return $country;
            }
        }
        
        return false;
    }
    
    /**
     * Get customer IP address
     * 
     * @return string
     */
    public static function get_customer_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR',               // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Check if IP is local
     * 
     * @param string $ip
     * @return bool
     */
    private static function is_local_ip($ip) {
        return in_array($ip, ['127.0.0.1', '::1']) || 
               strpos($ip, '192.168.') === 0 ||
               strpos($ip, '10.') === 0 ||
               strpos($ip, '172.16.') === 0;
    }
    
    /**
     * Set country cookie
     * 
     * @param string $country
     */
    private static function set_country_cookie($country) {
        setcookie(
            self::COUNTRY_COOKIE, 
            $country, 
            time() + (7 * DAY_IN_SECONDS), 
            COOKIEPATH, 
            COOKIE_DOMAIN, 
            is_ssl(), 
            true
        );
        $_COOKIE[self::COUNTRY_COOKIE] = $country;
    }
    
    /**
     * Get currency for country
     * 
     * @param string $country_code
     * @return string|false
     */
    public static function get_currency_for_country($country_code) {
        $country_code = strtoupper($country_code);
        
        return self::$country_currency_map[$country_code] ?? false;
    }
    
    /**
     * Get all country-currency mappings
     * 
     * @return array
     */
    public static function get_country_currency_map() {
        return self::$country_currency_map;
    }
    
    /**
     * Get countries for a specific currency
     * 
     * @param string $currency
     * @return array
     */
    public static function get_countries_for_currency($currency) {
        $countries = [];
        
        foreach (self::$country_currency_map as $country => $curr) {
            if ($curr === $currency) {
                $countries[] = $country;
            }
        }
        
        return $countries;
    }
    
    /**
     * AJAX: Get customer location info
     */
    public function ajax_get_location() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        $country = self::get_country();
        $currency = $country ? self::get_currency_for_country($country) : false;
        
        wp_send_json_success([
            'country' => $country,
            'suggested_currency' => $currency,
        ]);
    }
}
