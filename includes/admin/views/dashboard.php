<?php
/**
 * Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = SDMC_Settings::get_settings();
$license = SDMC_License::get_license();
$currencies = SDMC_Settings::get_active_currencies();
?>

<div class="wrap sdmc-dashboard">
    <h1>SD MultiCurrency Pro Dashboard</h1>
    
    <div class="sdmc-dashboard-grid">
        <!-- Status Card -->
        <div class="sdmc-card">
            <h2>Plugin Status</h2>
            <table class="form-table">
                <tr>
                    <th>Version</th>
                    <td><?php echo esc_html(SDMC_VERSION); ?></td>
                </tr>
                <tr>
                    <th>Base Currency</th>
                    <td><?php echo esc_html($settings['base_currency'] ?? 'ZAR'); ?></td>
                </tr>
                <tr>
                    <th>Active Currencies</th>
                    <td><?php echo esc_html(implode(', ', $currencies)); ?></td>
                </tr>
                <tr>
                    <th>License Status</th>
                    <td>
                        <span class="sdmc-badge sdmc-badge-<?php echo ($license['status'] ?? '') === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo esc_html(ucfirst($license['status'] ?? 'inactive')); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Quick Actions -->
        <div class="sdmc-card">
            <h2>Quick Actions</h2>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sdmc-settings')); ?>" class="button button-primary">
                    Configure Settings
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sdmc-license')); ?>" class="button">
                    Manage License
                </a>
            </p>
        </div>
        
        <!-- Usage -->
        <div class="sdmc-card">
            <h2>Shortcode Usage</h2>
            <p>Add the currency switcher to your site:</p>
            <code>[sd_currency_switcher]</code>
            <p class="description">Optional attributes: <code>style="dropdown|buttons|flags"</code></p>
        </div>
    </div>
</div>

<style>
.sdmc-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.sdmc-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.sdmc-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.sdmc-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.sdmc-badge-success {
    background: #d4edda;
    color: #155724;
}
.sdmc-badge-warning {
    background: #fff3cd;
    color: #856404;
}
</style>
