<?php
/**
 * Plugin Name: SD MultiCurrency Pro
 * Plugin URI: https://softdynamix.co.za/plugins/sd-multicurrency-pro
 * Description: Multi-currency pricing for WooCommerce + Tutor LMS. Display prices in multiple currencies while charging in ZAR. Perfect for South African businesses using Yoco.
 * Version: 1.0.4
 * Author: Soft Dynamix
 * Author URI: https://softdynamix.co.za
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sd-multicurrency-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin Constants
define('SDMC_VERSION', '1.0.4');
define('SDMC_PATH', plugin_dir_path(__FILE__));
define('SDMC_URL', plugin_dir_url(__FILE__));
define('SDMC_BASENAME', plugin_basename(__FILE__));
define('SDMC_PLUGIN_NAME', 'SD MultiCurrency Pro');

/**
 * Declare HPOS compatibility for WooCommerce
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        try {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        } catch (Exception $e) {
            // Silently fail if compatibility declaration doesn't work
        }
    }
});

/**
 * Check for required dependencies
 */
add_action('admin_init', 'sdmc_check_dependencies');
function sdmc_check_dependencies() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>' . esc_html(SDMC_PLUGIN_NAME) . '</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
}

/**
 * Include required files
 */
function sdmc_include_files() {
    // Core classes
    require_once SDMC_PATH . 'includes/core/class-currency.php';
    require_once SDMC_PATH . 'includes/core/class-settings.php';
    require_once SDMC_PATH . 'includes/core/class-helpers.php';
    require_once SDMC_PATH . 'includes/core/class-license.php';
    
    // Integrations
    require_once SDMC_PATH . 'includes/integrations/class-woocommerce.php';
    require_once SDMC_PATH . 'includes/integrations/class-tutor.php';
    
    // Admin
    if (is_admin()) {
        require_once SDMC_PATH . 'includes/admin/class-admin-menu.php';
        require_once SDMC_PATH . 'includes/admin/class-product-fields.php';
        require_once SDMC_PATH . 'includes/admin/class-onboarding.php';
    }
    
    // Frontend - always load for shortcode support in widgets/customizer
    require_once SDMC_PATH . 'includes/frontend/class-switcher.php';
    require_once SDMC_PATH . 'includes/frontend/class-display.php';
}

// Include files early
sdmc_include_files();

/**
 * Initialize Plugin
 */
add_action('plugins_loaded', 'sdmc_init', 20);
function sdmc_init() {
    // Load text domain
    load_plugin_textdomain('sd-multicurrency-pro', false, dirname(SDMC_BASENAME) . '/languages');
    
    // Check WooCommerce exists
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Initialize Core Classes
    SDMC_Currency::get_instance();
    SDMC_Settings::get_instance();
    SDMC_Helpers::get_instance();
    
    // Initialize WooCommerce Integration
    SDMC_Integrations_Woocommerce::get_instance();
    
    // Initialize Tutor LMS Integration
    if (class_exists('Tutor')) {
        SDMC_Integrations_Tutor::get_instance();
    }
    
    // Initialize Admin
    if (is_admin()) {
        SDMC_Admin_Menu::get_instance();
        SDMC_Admin_ProductFields::get_instance();
        SDMC_Admin_Onboarding::get_instance();
    }
    
    // Initialize Frontend (always initialize for shortcode support)
    SDMC_Frontend_Switcher::get_instance();
    SDMC_Frontend_Display::get_instance();
    
    // Initialize License System
    SDMC_License::get_instance();
}

/**
 * Activation Hook
 */
register_activation_hook(__FILE__, 'sdmc_activate');
function sdmc_activate() {
    // Set default options
    $default_settings = [
        'base_currency' => 'ZAR',
        'active_currencies' => ['ZAR', 'USD', 'GBP', 'EUR'],
        'symbol_map' => [
            'ZAR' => 'R',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€'
        ],
        'checkout_notice' => 1,
        'pricing_mode' => 'manual',
        'show_flag' => 1,
        'switcher_style' => 'dropdown'
    ];
    
    add_option('sdmc_settings', $default_settings);
    // Don't run wizard - disabled to prevent conflicts
    // add_option('sdmc_run_wizard', true);
    
    // Create license option with pre-activated license
    // Use gmdate() to avoid timezone warnings
    $expiry_date = gmdate('Y-m-d', strtotime('+1 year'));
    
    add_option('sdmc_license', [
        'key' => 'SD-MCP-AE5481FD-622BBD0F-E25E1993',
        'status' => 'active',
        'expires' => $expiry_date,
        'site' => home_url(),
        'activated_at' => current_time('mysql'),
        'error' => ''
    ]);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Deactivation Hook
 */
register_deactivation_hook(__FILE__, 'sdmc_deactivate');
function sdmc_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('sdmc_daily_license_check');
    
    flush_rewrite_rules();
}

/**
 * Enqueue Admin Assets
 */
add_action('admin_enqueue_scripts', 'sdmc_admin_assets');
function sdmc_admin_assets($hook) {
    $screen = get_current_screen();
    
    if (!$screen) {
        return;
    }
    
    if (strpos($hook, 'sdmc') !== false || ($screen && $screen->post_type === 'product')) {
        wp_enqueue_style('sdmc-admin', SDMC_URL . 'assets/css/admin.css', [], SDMC_VERSION);
        wp_enqueue_script('sdmc-admin', SDMC_URL . 'assets/js/admin.js', ['jquery'], SDMC_VERSION, true);
        
        wp_localize_script('sdmc-admin', 'sdmc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sdmc_nonce')
        ]);
    }
}

/**
 * Enqueue Frontend Assets
 */
add_action('wp_enqueue_scripts', 'sdmc_frontend_assets');
function sdmc_frontend_assets() {
    wp_enqueue_style('sdmc-frontend', SDMC_URL . 'assets/css/frontend.css', [], SDMC_VERSION);
    wp_enqueue_script('sdmc-frontend', SDMC_URL . 'assets/js/frontend.js', ['jquery'], SDMC_VERSION, true);
    
    $current_currency = 'ZAR';
    if (class_exists('SDMC_Currency')) {
        $current_currency = SDMC_Currency::get_currency();
    }
    
    wp_localize_script('sdmc-frontend', 'sdmc_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sdmc_nonce'),
        'current_currency' => $current_currency
    ]);
}
