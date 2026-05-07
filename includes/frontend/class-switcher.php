<?php
/**
 * Frontend Switcher Class
 * 
 * Handles currency switcher display
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Frontend_Switcher {
    
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
        // Register shortcodes (both variants for compatibility)
        add_shortcode('sd_currency_switcher', [$this, 'render_switcher']);
        add_shortcode('sdmc_currency_switcher', [$this, 'render_switcher']);
    }
    
    /**
     * Render currency switcher
     */
    public function render_switcher($atts = []) {
        $atts = shortcode_atts([
            'style' => SDMC_Settings::get_switcher_style(),
            'show_flag' => SDMC_Settings::get('show_flag', 1)
        ], $atts);
        
        $current_currency = SDMC_Currency::get_currency();
        $currencies = SDMC_Currency::get_active_currencies();
        
        if (empty($currencies)) {
            return '';
        }
        
        ob_start();
        
        echo '<div class="sdmc-currency-switcher sdmc-style-' . esc_attr($atts['style']) . '">';
        
        if ($atts['style'] === 'dropdown') {
            $this->render_dropdown($current_currency, $currencies);
        } elseif ($atts['style'] === 'buttons') {
            $this->render_buttons($current_currency, $currencies);
        } else {
            $this->render_flags($current_currency, $currencies);
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Render dropdown style
     */
    private function render_dropdown($current, $currencies) {
        echo '<div class="sdmc-dropdown-wrapper" style="display: inline-flex; align-items: center; gap: 5px;">';
        
        echo '<select class="sdmc-currency-select">';
        
        foreach ($currencies as $currency) {
            $symbol = SDMC_Currency::get_symbol($currency);
            $selected = ($currency === $current) ? 'selected' : '';
            echo '<option value="' . esc_attr($currency) . '" ' . esc_attr($selected) . '>';
            echo esc_html($symbol . ' ' . $currency);
            echo '</option>';
        }
        
        echo '</select>';
        
        // Reset button - detect based on geolocation
        echo '<button type="button" class="sdmc-reset-btn" title="Auto-detect currency based on your location" style="background: transparent; border: 1px solid #ccc; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 12px;">';
        echo '📍';
        echo '</button>';
        
        echo '</div>';
    }
    
    /**
     * Render buttons style
     */
    private function render_buttons($current, $currencies) {
        echo '<div class="sdmc-currency-buttons">';
        
        foreach ($currencies as $currency) {
            $symbol = SDMC_Currency::get_symbol($currency);
            $active_class = ($currency === $current) ? 'sdmc-active' : '';
            
            echo '<button type="button" class="sdmc-currency-btn ' . esc_attr($active_class) . '" data-currency="' . esc_attr($currency) . '">';
            echo esc_html($currency);
            echo '</button>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render flags style
     */
    private function render_flags($current, $currencies) {
        $flag_map = [
            'ZAR' => '🇿🇦',
            'USD' => '🇺🇸',
            'GBP' => '🇬🇧',
            'EUR' => '🇪🇺',
            'AUD' => '🇦🇺',
            'CAD' => '🇨🇦',
            'NZD' => '🇳🇿',
        ];
        
        echo '<div class="sdmc-currency-flags">';
        
        foreach ($currencies as $currency) {
            $flag = $flag_map[$currency] ?? '🏳️';
            $symbol = SDMC_Currency::get_symbol($currency);
            $active_class = ($currency === $current) ? 'sdmc-active' : '';
            
            echo '<button type="button" class="sdmc-currency-flag ' . esc_attr($active_class) . '" data-currency="' . esc_attr($currency) . '" title="' . esc_attr($symbol . $currency) . '">';
            echo esc_html($flag);
            echo '</button>';
        }
        
        echo '</div>';
    }
}
