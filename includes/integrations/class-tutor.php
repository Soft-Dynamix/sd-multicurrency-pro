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
        
        // Debug: Log price queries
        // add_action('get_post_metadata', [$this, 'debug_price_meta'], 1, 4);
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
        
        // Get our custom price for this currency
        $custom_price = get_post_meta($object_id, '_sd_price_' . strtolower($currency), true);
        
        // Debug
        error_log("SDMC Debug: Course $object_id, Currency: $currency, Custom Price: $custom_price");
        
        // Return the custom price if set
        if (!empty($custom_price) && is_numeric($custom_price)) {
            error_log("SDMC Debug: Returning custom price $custom_price for course $object_id");
            return $custom_price;
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
        
        // Get custom price
        $custom_price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
        
        error_log("SDMC Debug: filter_tutor_price_output - Course $course_id, Currency: $currency, Price: $custom_price");
        
        if (empty($custom_price) || !is_numeric($custom_price)) {
            return $price_html;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        
        return '<span class="sdmc-course-price">' . esc_html($symbol . number_format((float)$custom_price, 2)) . '</span>';
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
            $custom_price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
            
            error_log("SDMC Debug: Buffer - Course $course_id, Currency: $currency, Custom Price: $custom_price");
            
            if (!empty($custom_price) && is_numeric($custom_price)) {
                // Replace the price amount (e.g., R 800.00 → $ 50.00)
                $content = preg_replace(
                    '/' . preg_quote($base_symbol, '/') . '\s*[\d,]+\.?\d*/',
                    $symbol . ' ' . number_format((float)$custom_price, 2),
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
        
        $currencies = ['ZAR', 'USD', 'GBP', 'EUR'];
        $base_currency = $this->base_currency;
        
        echo '<div class="sdmc-course-pricing">';
        echo '<p class="description" style="margin-bottom: 15px;"><strong>Enter prices for each currency:</strong></p>';
        
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
            echo 'placeholder="0.00" ';
            echo 'style="width: 100%; padding: 5px;">';
            
            // Debug: Show what's actually saved
            echo '<small style="color: #666;">Saved value: ' . esc_html($price ?: '(empty)') . '</small>';
            echo '</div>';
        }
        
        echo '<p class="description" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; color: #666;">';
        echo '<strong>Important:</strong> Make sure to enter the price in that currency (not a conversion of the base price).';
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
            error_log("SDMC Debug: Nonce verification failed");
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            error_log("SDMC Debug: Permission check failed");
            return;
        }
        
        // Save prices
        $currencies = ['ZAR', 'USD', 'GBP', 'EUR'];
        
        foreach ($currencies as $currency) {
            $key = 'sdmc_price_' . strtolower($currency);
            
            if (isset($_POST[$key])) {
                $price = sanitize_text_field($_POST[$key]);
                
                $meta_key = '_sd_price_' . strtolower($currency);
                
                if (!empty($price) && is_numeric($price)) {
                    update_post_meta($post_id, $meta_key, floatval($price));
                    error_log("SDMC Debug: Saved $meta_key = $price for post $post_id");
                } else {
                    delete_post_meta($post_id, $meta_key);
                    error_log("SDMC Debug: Deleted $meta_key for post $post_id");
                }
            }
        }
        
        // Verify what was saved
        error_log("SDMC Debug: After save - verifying prices for post $post_id");
        foreach ($currencies as $currency) {
            $saved = get_post_meta($post_id, '_sd_price_' . strtolower($currency), true);
            error_log("SDMC Debug:   $currency = $saved");
        }
    }
}
