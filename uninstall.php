<?php
/**
 * SD MultiCurrency Pro - Uninstall
 * 
 * Clean up plugin data when uninstalled
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('sdmc_settings');
delete_option('sdmc_license');
delete_option('sdmc_run_wizard');

// Delete post meta for products
global $wpdb;

// Delete product currency prices
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
    WHERE meta_key LIKE '_sd_price_%'"
);

// Clear any scheduled cron jobs
wp_clear_scheduled_hook('sdmc_daily_license_check');

// Clear any transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_sdmc_%' 
    OR option_name LIKE '_site_transient_sdmc_%'"
);
