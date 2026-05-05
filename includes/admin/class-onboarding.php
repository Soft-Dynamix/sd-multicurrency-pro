<?php
/**
 * Admin Onboarding Class
 * 
 * Handles setup wizard - DISABLED by default to prevent conflicts
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_Admin_Onboarding {
    
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
        // Automatically clear wizard flag on activation to prevent redirect issues
        delete_option('sdmc_run_wizard');
        
        // Wizard disabled - no hooks added
        // add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);
        // add_action('admin_menu', [$this, 'add_wizard_page']);
    }
    
    /**
     * Maybe redirect to wizard - DISABLED
     */
    public function maybe_redirect_to_wizard() {
        // Disabled to prevent white screen of death
        return;
        
        // Only on admin
        if (!is_admin()) {
            return;
        }
        
        // Check if wizard should run
        $run_wizard = get_option('sdmc_run_wizard', false);
        
        if (!$run_wizard) {
            return;
        }
        
        // Don't redirect on AJAX
        if (wp_doing_ajax()) {
            return;
        }
        
        // Check if we're already on wizard page
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === 'admin_page_sdmc-wizard') {
            return;
        }
        
        // Redirect to wizard
        wp_safe_redirect(admin_url('admin.php?page=sdmc-wizard'));
        exit;
    }
    
    /**
     * Add wizard page
     */
    public function add_wizard_page() {
        add_submenu_page(
            null, // Hidden from menu
            'SD MultiCurrency Pro Setup',
            'Setup Wizard',
            'manage_options',
            'sdmc-wizard',
            [$this, 'render_wizard']
        );
    }
    
    /**
     * Render wizard
     */
    public function render_wizard() {
        // Mark wizard as complete immediately
        delete_option('sdmc_run_wizard');
        
        // Redirect to dashboard
        wp_safe_redirect(admin_url('admin.php?page=sd-multicurrency-pro'));
        exit;
    }
}
