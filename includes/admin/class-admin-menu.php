<?php
/**
 * Admin Menu Class
 * 
 * Handles admin menu and pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Admin_Menu {
    
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
        add_action('admin_menu', [$this, 'add_menu_pages']);
    }
    
    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        // Main menu
        add_menu_page(
            'SD MultiCurrency Pro',
            'MultiCurrency Pro',
            'manage_options',
            'sd-multicurrency-pro',
            [$this, 'render_dashboard'],
            'dashicons-money-alt',
            56
        );
        
        // Dashboard
        add_submenu_page(
            'sd-multicurrency-pro',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'sd-multicurrency-pro',
            [$this, 'render_dashboard']
        );
        
        // Settings
        add_submenu_page(
            'sd-multicurrency-pro',
            'Settings',
            'Settings',
            'manage_options',
            'sdmc-settings',
            [$this, 'render_settings']
        );
        
        // Exchange Rates
        add_submenu_page(
            'sd-multicurrency-pro',
            'Exchange Rates',
            'Exchange Rates',
            'manage_options',
            'sdmc-exchange-rates',
            [$this, 'render_exchange_rates']
        );
        
        // License
        add_submenu_page(
            'sd-multicurrency-pro',
            'License',
            'License',
            'manage_options',
            'sdmc-license',
            [$this, 'render_license']
        );
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        include SDMC_PATH . 'includes/admin/views/dashboard.php';
    }
    
    /**
     * Render settings
     */
    public function render_settings() {
        include SDMC_PATH . 'includes/admin/views/settings.php';
    }
    
    /**
     * Render exchange rates
     */
    public function render_exchange_rates() {
        include SDMC_PATH . 'includes/admin/views/exchange-rates.php';
    }
    
    /**
     * Render license
     */
    public function render_license() {
        include SDMC_PATH . 'includes/admin/views/license.php';
    }
}
