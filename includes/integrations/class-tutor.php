<?php
/**
 * Tutor LMS Integration Class
 * 
 * Handles Tutor LMS-specific hooks and filters
 * 
 * Price Priority:
 * 1. Currency-specific price if set (e.g., _sd_price_usd = 35)
 * 2. Convert from base ZAR price using exchange rate
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Integrations_Tutor {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Settings
     */
    private $settings = [];
    
    /**
     * Base currency
     */
    private $base_currency = 'ZAR';
    
    /**
     * Course post type
     */
    private $course_post_type = 'courses';
    
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
        // Only initialize if Tutor LMS is active
        if (!class_exists('Tutor')) {
            return;
        }
        
        // Safely get course post type
        if (function_exists('tutor')) {
            $tutor = tutor();
            if (is_object($tutor) && isset($tutor->course_post_type)) {
                $this->course_post_type = $tutor->course_post_type;
            }
        }
        
        $this->settings = get_option('sdmc_settings', []);
        $this->base_currency = $this->settings['base_currency'] ?? 'ZAR';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add meta box for course pricing
        add_action('add_meta_boxes', [$this, 'add_course_meta_box']);
        
        // Save course meta
        add_action('save_post_' . $this->course_post_type, [$this, 'save_course_meta'], 10, 2);
        
        // Filter Tutor LMS price output - multiple approaches
        
        // Method 1: Filter the meta data directly (most reliable for Tutor)
        add_filter('get_post_metadata', [$this, 'filter_price_meta'], 999, 4);
        
        // Method 2: Filter tutor price functions if they exist
        add_filter('tutor_course_price', [$this, 'filter_tutor_price_output'], 999, 2);
        add_filter('get_tutor_course_price', [$this, 'filter_tutor_price_output'], 999, 2);
        
        // Method 3: Output buffer for late price replacement
        add_action('tutor_course/single/content/before', [$this, 'start_price_buffer'], 1);
        add_action('tutor_course/single/content/after', [$this, 'end_price_buffer'], 999);
        add_action('tutor_before_course_archive_loop', [$this, 'start_price_buffer'], 1);
        add_action('tutor_after_course_archive_loop', [$this, 'end_price_buffer'], 999);
    }
    
    /**
     * Get converted price for a course
     * 
     * Priority:
     * 1. Use currency-specific price if set (e.g., _sd_price_usd = 35)
     * 2. Otherwise, convert from ZAR using exchange rate
     * 
     * @param int $course_id
     * @param string $currency
     * @return float|false
     */
    private function get_converted_price($course_id, $currency) {
        if ($currency === $this->base_currency) {
            // Return the base price from Tutor
            $price = get_post_meta($course_id, '_tutor_regular_price', true);
            if (empty($price)) {
                $price = get_post_meta($course_id, '_tutor_course_price', true);
            }
            return $price ? (float)$price : false;
        }
        
        // First, check for currency-specific price
        $currency_price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
        
        if (!empty($currency_price) && is_numeric($currency_price)) {
            return (float)$currency_price;
        }
        
        // Fallback: Convert from ZAR using exchange rate
        if (!class_exists('SDMC_Exchange_Rates')) {
            return false;
        }
        
        // Get base ZAR price
        $zar_price = get_post_meta($course_id, '_tutor_regular_price', true);
        if (empty($zar_price)) {
            $zar_price = get_post_meta($course_id, '_tutor_course_price', true);
        }
        
        if (empty($zar_price) || !is_numeric($zar_price)) {
            return false;
        }
        
        // Get exchange rate and convert
        $rate = SDMC_Exchange_Rates::get_rate($currency);
        
        if (!$rate || $rate <= 0) {
            return false;
        }
        
        // Rate format: 1 ZAR = X units of currency
        // So: currency amount = ZAR price * rate
        $converted_price = (float)$zar_price * $rate;
        
        return round($converted_price, 2);
    }
    
    /**
     * Filter post metadata to return correct price for currency
     */
    public function filter_price_meta($metadata, $object_id, $meta_key, $single) {
        // Only filter Tutor LMS price meta keys
        $price_keys = ['_tutor_regular_price', '_tutor_sale_price', '_tutor_course_price', '_tutor_price'];
        
        if (!in_array($meta_key, $price_keys)) {
            return $metadata;
        }
        
        // Skip in admin (except AJAX)
        if (is_admin() && !wp_doing_ajax()) {
            return $metadata;
        }
        
        // Check if Currency class exists
        if (!class_exists('SDMC_Currency')) {
            return $metadata;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $metadata;
        }
        
        // Get converted price (currency-specific or converted from ZAR)
        $converted_price = $this->get_converted_price($object_id, $currency);
        
        // Return the converted price
        if ($converted_price !== false) {
            return $converted_price;
        }
        
        return $metadata;
    }
    
    /**
     * Filter Tutor price output
     */
    public function filter_tutor_price_output($price_html, $course_id = null) {
        // Skip in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        if (!$course_id) {
            $course_id = get_the_ID();
        }
        
        if (!$course_id) {
            return $price_html;
        }
        
        // Check currency
        if (!class_exists('SDMC_Currency')) {
            return $price_html;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get converted price (currency-specific or converted from ZAR)
        $converted_price = $this->get_converted_price($course_id, $currency);
        
        if ($converted_price === false) {
            return $price_html;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        
        return '<span class="sdmc-course-price">' . esc_html($symbol . number_format($converted_price, 2)) . '</span>';
    }
    
    /**
     * Start price buffer
     */
    public function start_price_buffer() {
        ob_start([$this, 'replace_prices_in_buffer']);
    }
    
    /**
     * End price buffer
     */
    public function end_price_buffer() {
        ob_end_flush();
    }
    
    /**
     * Replace prices in output buffer
     */
    public function replace_prices_in_buffer($content) {
        if (is_admin()) {
            return $content;
        }
        
        if (!class_exists('SDMC_Currency')) {
            return $content;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $content;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        // Get the current course ID
        $course_id = get_the_ID();
        
        if ($course_id) {
            // Get converted price (currency-specific or converted from ZAR)
            $converted_price = $this->get_converted_price($course_id, $currency);
            
            if ($converted_price !== false) {
                // Replace the price amount (e.g., R 800.00 → $ 43.20)
                $content = preg_replace(
                    '/' . preg_quote($base_symbol, '/') . '\s*[\d,]+\.?\d*/',
                    $symbol . ' ' . number_format($converted_price, 2),
                    $content
                );
            }
        }
        
        return $content;
    }
    
    /**
     * Add course meta box
     */
    public function add_course_meta_box() {
        add_meta_box(
            'sdmc_course_pricing',
            'Multi-Currency Pricing',
            [$this, 'render_course_meta_box'],
            $this->course_post_type,
            'side',
            'default'
        );
    }
    
    /**
     * Render course meta box
     */
    public function render_course_meta_box($post) {
        wp_nonce_field('sdmc_course_pricing', 'sdmc_course_pricing_nonce');
        
        $settings = get_option('sdmc_settings', []);
        $currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
        $base_currency = $this->base_currency;
        
        echo '<div class="sdmc-course-pricing">';
        echo '<p class="description" style="margin-bottom: 15px;"><strong>Enter prices for each currency:</strong></p>';
        echo '<p class="description" style="margin-bottom: 10px; color: #666; font-size: 12px;">Leave empty to auto-convert from ' . esc_html($base_currency) . ' using exchange rate.</p>';
        
        foreach ($currencies as $currency) {
            $symbol = SDMC_Currency::get_symbol($currency);
            $price = get_post_meta($post->ID, '_sd_price_' . strtolower($currency), true);
            $is_base = ($currency === $base_currency);
            
            echo '<div style="margin-bottom: 12px; padding: 8px; background: #f9f9f9; border-radius: 4px;">';
            echo '<label for="sdmc_course_price_' . esc_attr(strtolower($currency)) . '" style="display: block; margin-bottom: 5px; font-weight: 600;">';
            echo esc_html($symbol . ' ' . $currency);
            if ($is_base) {
                echo ' <span style="color: #0073aa; font-size: 11px;">(Base Currency)</span>';
            }
            echo '</label>';
            echo '<input type="number" step="0.01" min="0" ';
            echo 'id="sdmc_course_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'name="sdmc_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'value="' . esc_attr($price) . '" ';
            echo 'placeholder="' . ($is_base ? 'Set in Tutor pricing' : 'Auto-convert from ' . $base_currency) . '" ';
            echo 'style="width: 100%; padding: 5px;" ';
            echo ($is_base ? 'readonly style="background: #eee;"' : '') . '>';
            
            if (!$is_base && empty($price)) {
                // Show what the auto-converted price would be
                $zar_price = get_post_meta($post->ID, '_tutor_regular_price', true);
                if (empty($zar_price)) {
                    $zar_price = get_post_meta($post->ID, '_tutor_course_price', true);
                }
                if (!empty($zar_price) && class_exists('SDMC_Exchange_Rates')) {
                    $rate = SDMC_Exchange_Rates::get_rate($currency);
                    if ($rate > 0) {
                        $auto_price = (float)$zar_price * $rate;
                        echo '<small style="color: #666;">Auto: ~' . esc_html($symbol . number_format($auto_price, 2)) . '</small>';
                    }
                }
            }
            echo '</div>';
        }
        
        echo '<p class="description" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; color: #666; font-size: 11px;">';
        echo '<strong>Tip:</strong> Set custom prices for specific currencies, or leave empty to use automatic conversion from the exchange rate.';
        echo '</p>';
        
        echo '</div>';
    }
    
    /**
     * Save course meta
     */
    public function save_course_meta($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['sdmc_course_pricing_nonce']) || 
            !wp_verify_nonce($_POST['sdmc_course_pricing_nonce'], 'sdmc_course_pricing')) {
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
        $settings = get_option('sdmc_settings', []);
        $currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
        
        foreach ($currencies as $currency) {
            if ($currency === $this->base_currency) {
                continue; // Skip base currency
            }
            
            $key = 'sdmc_price_' . strtolower($currency);
            
            if (isset($_POST[$key])) {
                $price = sanitize_text_field($_POST[$key]);
                
                $meta_key = '_sd_price_' . strtolower($currency);
                
                if (!empty($price) && is_numeric($price)) {
                    update_post_meta($post_id, $meta_key, floatval($price));
                } else {
                    delete_post_meta($post_id, $meta_key);
                }
            }
        }
    }
}
