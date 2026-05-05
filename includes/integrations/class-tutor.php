<?php
/**
 * Tutor LMS Integration Class
 * 
 * Handles Tutor LMS-specific hooks and filters
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
        // Course price filter
        add_filter('tutor_course_price', [$this, 'course_price'], 10, 2);
        
        // Add meta box for course pricing
        add_action('add_meta_boxes', [$this, 'add_course_meta_box']);
        
        // Save course meta
        add_action('save_post_' . $this->course_post_type, [$this, 'save_course_meta'], 10, 2);
    }
    
    /**
     * Course price filter
     */
    public function course_price($price_html, $course_id) {
        // Don't modify in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        // Check if Currency class exists
        if (!class_exists('SDMC_Currency')) {
            return $price_html;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get custom price for currency
        $custom_price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
        
        if (empty($custom_price) || $custom_price <= 0) {
            return $price_html;
        }
        
        // Format price
        $symbol = SDMC_Currency::get_symbol($currency);
        
        return '<span class="sdmc-course-price">' . esc_html($symbol . number_format($custom_price, 2)) . '</span>';
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
        
        $currencies = SDMC_Settings::get_active_currencies();
        $base_currency = SDMC_Settings::get_base_currency();
        
        echo '<div class="sdmc-course-pricing">';
        echo '<p class="description">Set prices for each currency.</p>';
        
        foreach ($currencies as $currency) {
            $symbol = SDMC_Currency::get_symbol($currency);
            $price = get_post_meta($post->ID, '_sd_price_' . strtolower($currency), true);
            $is_base = ($currency === $base_currency);
            
            echo '<p>';
            echo '<label for="sdmc_course_price_' . esc_attr(strtolower($currency)) . '">';
            echo esc_html($symbol . ' ' . $currency);
            if ($is_base) {
                echo ' <span class="sdmc-base-label">(Base)</span>';
            }
            echo '</label><br>';
            echo '<input type="number" step="0.01" min="0" ';
            echo 'id="sdmc_course_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'name="sdmc_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'value="' . esc_attr($price) . '" ';
            echo 'style="width:100%">';
            echo '</p>';
        }
        
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
