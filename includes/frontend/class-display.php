<?php
/**
 * Frontend Display Class
 * 
 * Handles frontend price display modifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Frontend_Display {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Current currency cache
     */
    private $current_currency = null;
    
    /**
     * Base currency
     */
    private $base_currency = 'ZAR';
    
    /**
     * Course prices cache for JS
     */
    private $course_prices = [];
    
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
        $settings = get_option('sdmc_settings', []);
        $this->base_currency = $settings['base_currency'] ?? 'ZAR';
        
        // WooCommerce price filters
        add_filter('woocommerce_product_get_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'get_product_price'], 99, 2);
        
        // Variation prices
        add_filter('woocommerce_product_variation_get_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', [$this, 'get_product_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_sale_price', [$this, 'get_product_price'], 99, 2);
        
        // Price HTML filters
        add_filter('woocommerce_get_price_html', [$this, 'format_price_html'], 99, 2);
        
        // Cart item prices
        add_filter('woocommerce_cart_item_price', [$this, 'cart_item_price'], 99, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'cart_item_subtotal'], 99, 3);
        
        // WooCommerce currency filters
        add_filter('woocommerce_currency', [$this, 'change_woocommerce_currency'], 99);
        add_filter('woocommerce_currency_symbol', [$this, 'change_currency_symbol'], 99, 2);
        
        // Tutor LMS - Filter price meta directly (most reliable method)
        add_filter('get_post_metadata', [$this, 'filter_tutor_price_meta'], 999, 4);
        
        // Tutor LMS - Multiple filter hooks
        add_filter('tutor_course_price', [$this, 'tutor_course_price'], 999, 2);
        add_filter('get_tutor_course_price', [$this, 'tutor_course_price'], 999, 2);
        
        // Tutor LMS - Add data attributes to price elements
        add_action('wp_footer', [$this, 'output_price_data_script'], 999);
        
        // AJAX handler for getting converted prices
        add_action('wp_ajax_sdmc_get_course_prices', [$this, 'ajax_get_course_prices']);
        add_action('wp_ajax_nopriv_sdmc_get_course_prices', [$this, 'ajax_get_course_prices']);
        
        // Add checkout notice
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 5);
        
        // Add currency info to order emails
        add_action('woocommerce_email_after_order_table', [$this, 'email_currency_info'], 10, 4);
    }
    
    /**
     * Get current currency (cached)
     */
    private function get_current_currency() {
        if ($this->current_currency === null) {
            $this->current_currency = SDMC_Currency::get_currency();
        }
        return $this->current_currency;
    }
    
    /**
     * Change WooCommerce currency
     */
    public function change_woocommerce_currency($currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $currency;
        }
        
        return $this->get_current_currency();
    }
    
    /**
     * Change WooCommerce currency symbol
     */
    public function change_currency_symbol($symbol, $currency) {
        if (is_admin() && !wp_doing_ajax()) {
            return $symbol;
        }
        
        return SDMC_Currency::get_symbol($currency);
    }
    
    /**
     * Get product price in current currency
     */
    public function get_product_price($price, $product) {
        // Skip if in admin or no product
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }
        
        // Skip for base currency
        $currency = $this->get_current_currency();
        
        if ($currency === $this->base_currency) {
            return $price;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        
        // Check for variation parent
        if (is_object($product) && method_exists($product, 'is_type') && $product->is_type('variation')) {
            $product_id = $product->get_parent_id();
        }
        
        // Get currency-specific price
        $currency_price = get_post_meta($product_id, '_sd_price_' . strtolower($currency), true);
        
        // If no price set, return original (fallback to base)
        if (empty($currency_price) || !is_numeric($currency_price)) {
            return $price;
        }
        
        return wc_format_decimal($currency_price);
    }
    
    /**
     * Format price HTML with currency symbol
     */
    public function format_price_html($price_html, $product) {
        // Skip if in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        $currency = $this->get_current_currency();
        
        // Only modify if not base currency
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get the symbol for current currency
        $symbol = SDMC_Currency::get_symbol($currency);
        
        // Replace the symbol in price HTML
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        $price_html = str_replace($base_symbol, $symbol, $price_html);
        
        return $price_html;
    }
    
    /**
     * Filter cart item price
     */
    public function cart_item_price($price, $cart_item, $cart_item_key) {
        $currency = $this->get_current_currency();
        
        if ($currency === $this->base_currency) {
            return $price;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        return str_replace($base_symbol, $symbol, $price);
    }
    
    /**
     * Filter cart item subtotal
     */
    public function cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        $currency = $this->get_current_currency();
        
        if ($currency === $this->base_currency) {
            return $subtotal;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        return str_replace($base_symbol, $symbol, $subtotal);
    }
    
    /**
     * Filter Tutor LMS course price - main filter
     */
    public function tutor_course_price($price_html, $course_id = null) {
        // Skip if in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $price_html;
        }
        
        if (!$course_id) {
            $course_id = get_the_ID();
        }
        
        if (!$course_id) {
            return $price_html;
        }
        
        $currency = $this->get_current_currency();
        
        // If base currency, return as-is
        if ($currency === $this->base_currency) {
            return $price_html;
        }
        
        // Get currency-specific price
        $currency_price = $this->get_course_currency_price($course_id, $currency);
        
        if ($currency_price === false) {
            return $price_html;
        }
        
        $symbol = SDMC_Currency::get_symbol($currency);
        
        return '<span class="sdmc-price" data-course-id="' . esc_attr($course_id) . '">' . esc_html($symbol . number_format($currency_price, 2)) . '</span>';
    }
    
    /**
     * Filter Tutor LMS price meta directly - catches the actual price value
     */
    public function filter_tutor_price_meta($metadata, $object_id, $meta_key, $single) {
        // Only filter Tutor price meta keys
        if (!in_array($meta_key, ['_tutor_regular_price', '_tutor_sale_price', '_tutor_course_price'])) {
            return $metadata;
        }
        
        // Skip in admin
        if (is_admin() && !wp_doing_ajax()) {
            return $metadata;
        }
        
        $currency = $this->get_current_currency();
        
        // Skip if base currency
        if ($currency === $this->base_currency) {
            return $metadata;
        }
        
        // Get our custom price for this currency
        $custom_price = get_post_meta($object_id, '_sd_price_' . strtolower($currency), true);
        
        if (!empty($custom_price) && is_numeric($custom_price)) {
            // Store for JS
            $this->course_prices[$object_id] = $this->course_prices[$object_id] ?? [];
            $this->course_prices[$object_id][$currency] = $custom_price;
            
            return $custom_price;
        }
        
        return $metadata;
    }
    
    /**
     * Get course price for a specific currency
     */
    private function get_course_currency_price($course_id, $currency) {
        $price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
        
        if (empty($price) || !is_numeric($price)) {
            return false;
        }
        
        return (float) $price;
    }
    
    /**
     * AJAX handler to get course prices
     */
    public function ajax_get_course_prices() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        $currency = sanitize_text_field($_POST['currency'] ?? '');
        $course_ids = isset($_POST['course_ids']) ? array_map('intval', $_POST['course_ids']) : [];
        
        if (empty($currency) || empty($course_ids)) {
            wp_send_json_error(['message' => 'Missing parameters']);
        }
        
        $prices = [];
        $symbol = SDMC_Currency::get_symbol($currency);
        
        foreach ($course_ids as $course_id) {
            $price = get_post_meta($course_id, '_sd_price_' . strtolower($currency), true);
            
            if (!empty($price) && is_numeric($price)) {
                $prices[$course_id] = [
                    'price' => (float) $price,
                    'formatted' => $symbol . number_format((float) $price, 2)
                ];
            }
        }
        
        wp_send_json_success([
            'currency' => $currency,
            'symbol' => $symbol,
            'prices' => $prices
        ]);
    }
    
    /**
     * Output price data script in footer
     */
    public function output_price_data_script() {
        if (is_admin()) {
            return;
        }
        
        $currency = $this->get_current_currency();
        $symbol = SDMC_Currency::get_symbol($currency);
        $base_symbol = SDMC_Currency::get_symbol($this->base_currency);
        
        ?>
        <script type="text/javascript">
        (function($) {
            'use strict';
            
            var sdmcData = {
                currency: '<?php echo esc_js($currency); ?>',
                baseCurrency: '<?php echo esc_js($this->base_currency); ?>',
                symbol: '<?php echo esc_js($symbol); ?>',
                baseSymbol: '<?php echo esc_js($base_symbol); ?>',
                ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('sdmc_nonce')); ?>'
            };
            
            // Find all course IDs on the page
            var courseIds = [];
            $('[data-course-id]').each(function() {
                var id = $(this).data('course-id');
                if (id && courseIds.indexOf(id) === -1) {
                    courseIds.push(id);
                }
            });
            
            // Also try to find course IDs from Tutor LMS elements
            $('.tutor-course-loop, .tutor-course-card, article.courses').each(function() {
                var id = $(this).data('id') || $(this).attr('id');
                if (id) {
                    id = String(id).replace(/\D/g, '');
                    if (id && courseIds.indexOf(parseInt(id)) === -1) {
                        courseIds.push(parseInt(id));
                    }
                }
            });
            
            // Update prices if not base currency
            if (sdmcData.currency !== sdmcData.baseCurrency && courseIds.length > 0) {
                updateCoursePrices(courseIds);
            }
            
            function updateCoursePrices(courseIds) {
                $.ajax({
                    url: sdmcData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sdmc_get_course_prices',
                        nonce: sdmcData.nonce,
                        currency: sdmcData.currency,
                        course_ids: courseIds
                    },
                    success: function(response) {
                        if (response.success && response.data.prices) {
                            $.each(response.data.prices, function(courseId, priceData) {
                                // Update price elements
                                $('[data-course-id="' + courseId + '"]').text(priceData.formatted);
                                
                                // Also update elements that might contain this course's price
                                updatePriceInDOM(courseId, priceData);
                            });
                        }
                    }
                });
            }
            
            function updatePriceInDOM(courseId, priceData) {
                // Try to find and update Tutor LMS price elements
                // This is a fallback for elements without data-course-id
                
                // Look for price elements near course cards
                var $course = $('[data-id="' + courseId + '"], .tutor-course-card[data-id="' + courseId + '"]');
                if ($course.length) {
                    $course.find('.tutor-course-price, .price, .tutor-price').each(function() {
                        var $el = $(this);
                        var text = $el.text();
                        // Replace the price pattern
                        if (text.match(new RegExp(sdmcData.baseSymbol + '\\s*[\\d,]+\\.?\\d*'))) {
                            $el.text(priceData.formatted);
                        }
                    });
                }
            }
            
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Display checkout notice
     */
    public function checkout_notice() {
        $settings = get_option('sdmc_settings', []);
        
        if (empty($settings['checkout_notice'])) {
            return;
        }
        
        $base_currency = $settings['base_currency'] ?? 'ZAR';
        $symbol = SDMC_Currency::get_symbol($base_currency);
        
        echo '<div class="sdmc-checkout-notice woocommerce-info">';
        echo sprintf(
            esc_html__('All transactions are processed in %s (%s). Currency conversion is for display purposes only.', 'sd-multicurrency-pro'),
            esc_html($base_currency),
            esc_html($symbol)
        );
        echo '</div>';
    }
    
    /**
     * Add currency info to emails
     */
    public function email_currency_info($order, $sent_to_admin, $plain_text, $email) {
        $settings = get_option('sdmc_settings', []);
        $base_currency = $settings['base_currency'] ?? 'ZAR';
        $symbol = SDMC_Currency::get_symbol($base_currency);
        
        if ($plain_text) {
            echo "\n" . sprintf(__('Payment processed in %s (%s)', 'sd-multicurrency-pro'), $base_currency, $symbol) . "\n";
        } else {
            echo '<p><small>' . sprintf(__('Payment processed in %s (%s)', 'sd-multicurrency-pro'), esc_html($base_currency), esc_html($symbol)) . '</small></p>';
        }
    }
}
