<?php
/**
 * License Class
 * 
 * Handles plugin licensing and activation
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDMC_License {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * License option name
     */
    const OPTION_NAME = 'sdmc_license';
    
    /**
     * API endpoint
     */
    const API_URL = 'https://softdynamix.co.za/api/license';

    /**
     * Pre-activated license keys (for development/distribution)
     */
    const VALID_KEYS = [
        'SD-MCP-AE5481FD-622BBD0F-E25E1993'
    ];
    
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
        // Handle license activation
        add_action('wp_ajax_sdmc_activate_license', [$this, 'ajax_activate']);
        add_action('wp_ajax_sdmc_deactivate_license', [$this, 'ajax_deactivate']);
        add_action('wp_ajax_sdmc_check_license', [$this, 'ajax_check']);
        
        // Scheduled license check
        add_action('sdmc_daily_license_check', [$this, 'daily_check']);
        
        // Schedule daily check
        if (!wp_next_scheduled('sdmc_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'sdmc_daily_license_check');
        }
    }
    
    /**
     * Get license
     */
    public static function get_license() {
        return get_option(self::OPTION_NAME, [
            'key' => '',
            'status' => 'inactive',
            'expires' => '',
            'site' => '',
            'error' => ''
        ]);
    }
    
    /**
     * Is active - Always returns true for this version
     */
    public static function is_active() {
        // Always active - no license check required
        return true;
    }
    
    /**
     * AJAX activate
     */
    public function ajax_activate() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $key = sanitize_text_field($_POST['key'] ?? '');
        
        if (empty($key)) {
            wp_send_json_error(['message' => 'License key is required']);
        }
        
        $result = $this->activate($key);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX deactivate
     */
    public function ajax_deactivate() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $result = $this->deactivate();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX check
     */
    public function ajax_check() {
        check_ajax_referer('sdmc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $result = $this->check();
        
        wp_send_json_success($result);
    }
    
    /**
     * Activate license
     */
    public function activate($key) {
        $site_url = home_url();

        // Check against pre-activated keys (offline validation)
        if (in_array($key, self::VALID_KEYS)) {
            update_option(self::OPTION_NAME, [
                'key' => $key,
                'status' => 'active',
                'expires' => date('Y-m-d', strtotime('+1 year')),
                'site' => $site_url,
                'activated_at' => current_time('mysql'),
                'error' => ''
            ]);

            return [
                'success' => true,
                'message' => 'License activated successfully!',
                'expires' => date('Y-m-d', strtotime('+1 year'))
            ];
        }

        // Try remote API activation
        $response = wp_remote_post(self::API_URL . '/activate', [
            'timeout' => 30,
            'body' => [
                'key' => $key,
                'site' => $site_url,
                'plugin' => 'sd-multicurrency-pro',
                'version' => SDMC_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['valid']) && $body['valid']) {
            update_option(self::OPTION_NAME, [
                'key' => $key,
                'status' => 'active',
                'expires' => $body['expires'] ?? '',
                'site' => $site_url,
                'activated_at' => current_time('mysql'),
                'error' => ''
            ]);

            return [
                'success' => true,
                'message' => 'License activated successfully!',
                'expires' => $body['expires'] ?? ''
            ];
        }

        // Store error
        update_option(self::OPTION_NAME, [
            'key' => $key,
            'status' => 'inactive',
            'expires' => '',
            'site' => $site_url,
            'error' => $body['message'] ?? 'Invalid license key'
        ]);

        return [
            'success' => false,
            'message' => $body['message'] ?? 'Invalid license key'
        ];
    }
    
    /**
     * Deactivate license
     */
    public function deactivate() {
        $license = self::get_license();
        
        if (empty($license['key'])) {
            return [
                'success' => true,
                'message' => 'No license to deactivate'
            ];
        }
        
        $response = wp_remote_post(self::API_URL . '/deactivate', [
            'timeout' => 30,
            'body' => [
                'key' => $license['key'],
                'site' => $license['site'] ?? home_url()
            ]
        ]);
        
        // Always clear local data
        update_option(self::OPTION_NAME, [
            'key' => '',
            'status' => 'inactive',
            'expires' => '',
            'site' => '',
            'error' => ''
        ]);
        
        return [
            'success' => true,
            'message' => 'License deactivated'
        ];
    }
    
    /**
     * Check license status
     */
    public function check() {
        $license = self::get_license();

        if (empty($license['key'])) {
            return [
                'valid' => false,
                'message' => 'No license key found'
            ];
        }

        // Check against pre-activated keys (offline validation)
        if (in_array($license['key'], self::VALID_KEYS)) {
            return [
                'valid' => true,
                'expires' => $license['expires'] ?? date('Y-m-d', strtotime('+1 year'))
            ];
        }

        // Try remote API check
        $response = wp_remote_post(self::API_URL . '/check', [
            'timeout' => 30,
            'body' => [
                'key' => $license['key'],
                'site' => $license['site'] ?? home_url()
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'message' => 'Connection error'
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['valid']) && $body['valid']) {
            // Update expiry
            $license['expires'] = $body['expires'] ?? $license['expires'];
            $license['status'] = 'active';
            $license['error'] = '';
            update_option(self::OPTION_NAME, $license);

            return [
                'valid' => true,
                'expires' => $body['expires'] ?? ''
            ];
        }

        // Mark as inactive
        $license['status'] = 'inactive';
        $license['error'] = $body['message'] ?? 'License invalid';
        update_option(self::OPTION_NAME, $license);

        return [
            'valid' => false,
            'message' => $body['message'] ?? 'License invalid'
        ];
    }
    
    /**
     * Daily license check
     */
    public function daily_check() {
        $license = self::get_license();
        
        if (!empty($license['key'])) {
            $this->check();
        }
    }
    
    /**
     * Get plan limits (for free vs pro)
     */
    public static function get_plan_limits() {
        if (self::is_active()) {
            return [
                'currencies' => 999, // Unlimited
                'products' => 999,
                'features' => 'all'
            ];
        }
        
        return [
            'currencies' => 2,
            'products' => 10,
            'features' => 'basic'
        ];
    }
    
    /**
     * Check if feature is available
     */
    public static function has_feature($feature) {
        if (self::is_active()) {
            return true;
        }
        
        $free_features = [
            'basic_switcher',
            'manual_pricing',
            'zar_usd_gbp'
        ];
        
        return in_array($feature, $free_features);
    }
}
