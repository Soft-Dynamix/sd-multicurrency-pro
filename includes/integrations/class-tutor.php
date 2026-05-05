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
     * Course IDs on current page
     */
    private $page_course_ids = [];
    
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
        // Filter the price meta directly - this is the most reliable method
        add_filter('get_post_metadata', [$this, 'filter_price_meta'], 999, 4);
        
        // Add data attributes to course cards
        add_action('tutor_course/before/loop', [$this, 'add_course_data_attribute'], 5);
        
        // Output course IDs for JS
        add_action('wp_footer', [$this, 'output_course_ids_js'], 999);
        
        // Add meta box for course pricing
        add_action('add_meta_boxes', [$this, 'add_course_meta_box']);
        
        // Save course meta
        add_action('save_post_' . $this->course_post_type, [$this, 'save_course_meta'], 10, 2);
        
        // Track which courses are displayed
        add_filter('the_posts', [$this, 'track_displayed_courses'], 999, 2);
    }
    
    /**
     * Filter post metadata to return correct price for currency
     */
    public function filter_price_meta($metadata, $object_id, $meta_key, $single) {
        // Only filter Tutor LMS price meta
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
        
        // Track this course ID
        if (!in_array($object_id, $this->page_course_ids)) {
            $this->page_course_ids[] = $object_id;
        }
        
        // Get our custom price for this currency
        $custom_price = get_post_meta($object_id, '_sd_price_' . strtolower($currency), true);
        
        // Return the custom price if set
        if (!empty($custom_price) && is_numeric($custom_price)) {
            return $custom_price;
        }
        
        return $metadata;
    }
    
    /**
     * Add data attribute to course loop items
     */
    public function add_course_data_attribute() {
        global $post;
        if ($post && $post->post_type === $this->course_post_type) {
            echo ' data-course-id="' . esc_attr($post->ID) . '" ';
        }
    }
    
    /**
     * Track displayed courses
     */
    public function track_displayed_courses($posts, $query) {
        if (is_admin()) {
            return $posts;
        }
        
        foreach ($posts as $post) {
            if ($post->post_type === $this->course_post_type) {
                if (!in_array($post->ID, $this->page_course_ids)) {
                    $this->page_course_ids[] = $post->ID;
                }
            }
        }
        
        return $posts;
    }
    
    /**
     * Output course IDs as JS variable
     */
    public function output_course_ids_js() {
        if (is_admin()) {
            return;
        }
        
        if (empty($this->page_course_ids)) {
            return;
        }
        
        $currency = SDMC_Currency::get_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        var sdmc_course_ids = <?php echo json_encode(array_values($this->page_course_ids)); ?>;
        </script>
        <?php
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
            echo '<label for="sdmc_course_price_' . esc_attr(strtolower($currency)) . '" style="display: block; margin-bottom: 5px; font-weight: 600;">';
            echo esc_html($symbol . ' ' . $currency);
            if ($is_base) {
                echo ' <span style="color: #0073aa;">(Base)</span>';
            }
            echo '</label>';
            echo '<input type="number" step="0.01" min="0" ';
            echo 'id="sdmc_course_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'name="sdmc_price_' . esc_attr(strtolower($currency)) . '" ';
            echo 'value="' . esc_attr($price) . '" ';
            echo 'placeholder="0.00" ';
            echo 'style="width: 100%;">';
            echo '</p>';
        }
        
        echo '<p class="description" style="margin-top: 10px; color: #666; font-style: italic;">';
        echo 'Leave empty to use the base currency price.';
        echo '</p>';
        
        echo '</div>';
        
        // Show current saved values
        echo '<script>
        jQuery(document).ready(function($) {
            console.log("Course prices for post ' . $post->ID . ':");
            ';
        foreach ($currencies as $currency) {
            $price = get_post_meta($post->ID, '_sd_price_' . strtolower($currency), true);
            echo 'console.log("  ' . $currency . ': " + "' . esc_js($price) . '");';
        }
        echo '
        });
        </script>';
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
