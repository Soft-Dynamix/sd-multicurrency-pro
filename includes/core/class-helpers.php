<?php
/**
 * Helpers Class
 * 
 * Utility functions for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Helpers {
    
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
     * Get product multi-currency prices
     */
    public static function get_product_prices($product_id) {
        $prices = [];
        $currencies = SDMC_Settings::get_active_currencies();
        
        foreach ($currencies as $currency) {
            $price = get_post_meta($product_id, '_sd_price_' . strtolower($currency), true);
            if (!empty($price)) {
                $prices[$currency] = (float) $price;
            }
        }
        
        return $prices;
    }
    
    /**
     * Save product multi-currency prices
     */
    public static function save_product_prices($product_id, $prices) {
        foreach ($prices as $currency => $price) {
            update_post_meta($product_id, '_sd_price_' . strtolower($currency), sanitize_text_field($price));
        }
    }
    
    /**
     * Get available currencies list
     */
    public static function get_available_currencies() {
        return [
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
            'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
        ];
    }
    
    /**
     * Check if plugin is licensed
     */
    public static function is_licensed() {
        if (class_exists('SDMC_License')) {
            return SDMC_License::is_active();
        }
        return true;
    }
}
