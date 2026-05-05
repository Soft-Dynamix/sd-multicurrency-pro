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
        // Multiple hooks for Tutor LMS price display
        add_filter('tutor_course_price', [$this, 'course_price'], 999, 2);
        add_filter('get_tutor_course_price', [$this, 'course_price'], 999, 2);
        add_filter('tutor/course/price', [$this, 'course_price'], 999, 2);
        
        // Hook into tutor price HTML output
        add_action('tutor_course/single/price_content_before', [$this, 'start_price_capture'], 1);
        add_action('tutor_course/single/price_content_after', [$this, 'end_price_capture'], 999);
        
        // Filter the course price meta
        add_filter('get_post_metadata', [$this, 'filter_course_price_meta'], 999, 4);
        
        // Add meta box for course pricing
        add_action('add_meta_boxes', [$this, 'add_course_meta_box']);
        
        // Save course meta
        add_action('save_post_' . $this->course_post_type, [$this, 'save_course_meta'], 10, 2);
        
        // Shortcode for course price
        add_shortcode('sdmc_course_price', [$this, 'render_course_price_shortcode']);
    }
    
    /**
     * Course price filter - main handler
     */
    public function course_price($price_html, $course_id = null) {
        // Don't modify in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        // Check if Currency class exists
        if (!class_exists('SDMC_Currency')) {
            return $price_html;
        }
        
        // Get course ID if not provided
        if (!$course_id) {
            $course_id = get_the_ID();
        }
        
        if (!$course_id) {
            return $price_html;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get custom price for currency
        $custom_price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
        
        // If no custom price, return original
        if (empty($custom_price) || !is_numeric($custom_price)) {
            return $price_html;
        }
        
        // Format price with symbol
        $symbol = SDMC_Currency::get_symbol($currency);
        
        return '<span class="sdmc-course-price">' . esc_html($symbol . number_format((float)$custom_price, 2)) . '</span>';
    }
    
    /**
     * Filter course price meta - catches direct meta queries
     */
    public function filter_course_price_meta($metadata, $object_id, $meta_key, $single) {
        // Only filter tutor course price meta
        if ($meta_key !== '_tutor_course_price_type' && $meta_key !== '_tutor_regular_price') {
            return $metadata;
        }
        
        // Skip in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $metadata;
        }
        
        // Check if this is a course
        $post_type = get_post_type($object_id);
        if ($post_type !== $this->course_post_type) {
            return $metadata;
        }
        
        // Check currency class
        if (!class_exists('SDMC_Currency')) {
            return $metadata;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $metadata;
        }
        
        // Get custom price
        $custom_price = get_post_meta($object_id, '_sd_price_' . strtolower($currency), true);
        
        if (!empty($custom_price) && is_numeric($custom_price)) {
            // Return the custom price instead
            return $custom_price;
        }
        
        return $metadata;
    }
    
    /**
     * Start price capture
     */
    public function start_price_capture() {
        ob_start();
    }
    
    /**
     * End price capture and modify
     */
    public function end_price_capture() {
        $content = ob_get_clean();
        
        // Check currency
        if (!class_exists('SDMC_Currency')) {
            echo $content;
            return;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        if ($currency === $this->base_currency) {
            echo $content;
            return;
        }
        
        // Replace currency symbol in output
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        $new_symbol = SDMC_Currency::get_symbol($currency);
        
        // Replace the symbol
        $content = str_replace($base_symbol, $new_symbol, $content);
        
        echo $content;
    }
    
    /**
     * Render course price shortcode
     */
    public function render_course_price_shortcode($atts) {
        $atts = shortcode_atts([
            'course_id' => get_the_ID(),
            'currency' => '',
        ], $atts);
        
        $course_id = intval($atts['course_id']);
        $currency = $atts['currency'] ?: SDMC_Currency::get_currency();
        
        if (!$course_id) {
            return '';
        }
        
        $price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
        
        if (empty($price) || !is_numeric($price)) {
            // Fallback to base price
            $price = get_post_meta($course_id, '_sd_price_' . strtolower($this->base_currency), true);
            $currency = $this->base_currency;
        }
        
        if (empty($price)) {
            return '';
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        
        return '<span class="sdmc-course-price">' . esc_html($symbol . number_format((float)$price, 2)) . '</span>';
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
        
        $currencies = ['ZAR', 'USD', 'GBP', 'EUR'];
        $base_currency = $this->base_currency;
        
        echo '<div class="sdmc-course-pricing">';
        echo '<p class="description" style="margin-bottom: 15px;">Set prices for each currency.</p>';
        
        foreach ($currencies as $currency) {
            $symbol = SDMC_Currency::get_symbol($currency);
            $price = get_post_meta($post->ID, '_sd_price_' . strtolower($currency), true);
            $is_base = ($currency === $base_currency);
            
            echo '<p style="margin-bottom: 10px;">';
            echo '<label for="sdmc_course_price_' . esc_attr(strtolower($currency)) . '" style="display: block; margin-bottom: 5px;">';
            echo esc_html($symbol . ' ' . $currency);
            if ($is_base) {
                echo ' <span style="color: #0073aa;">(Base)</span>';
            }
            echo '</label>';
            echo '<input type="number" step="0.01" min="0" ';
            echo 'id="sdmc_course_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'name="sdmc_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'value="' . esc_attr($price) . '" ';
            echo 'style="width: 100%;">';
            echo '</p>';
        }
        
        echo '<p class="description" style="margin-top: 10px; color: #666; font-style: italic;">';
        echo 'Leave empty to use the base currency price for that currency.';
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
        $currencies = ['ZAR', 'USD', 'GBP', 'EUR'];
        
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
