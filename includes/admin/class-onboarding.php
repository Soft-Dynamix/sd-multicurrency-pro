<?php
/**
 * Admin Onboarding Class
 * 
 * Handles setup wizard
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
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);
        add_action('admin_menu', [$this, 'add_wizard_page']);
    }
    
    /**
     * Maybe redirect to wizard
     */
    public function maybe_redirect_to_wizard() {
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
        $screen = get_current_screen();
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
        include SDMC_PATH . 'includes/admin/views/wizard/header.php';
        
        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        
        switch ($step) {
            case 2:
                include SDMC_PATH . 'includes/admin/views/wizard/step-2.php';
                break;
            case 3:
                include SDMC_PATH . 'includes/admin/views/wizard/step-3.php';
                break;
            case 4:
                include SDMC_PATH . 'includes/admin/views/wizard/step-4.php';
                break;
            default:
                include SDMC_PATH . 'includes/admin/views/wizard/step-1.php';
                break;
        }
        
        include SDMC_PATH . 'includes/admin/views/wizard/footer.php';
    }
}
