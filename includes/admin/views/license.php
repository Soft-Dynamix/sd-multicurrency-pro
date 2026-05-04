<?php
/**
 * License View
 */

if (!defined('ABSPATH')) {
    exit;
}

$license = SDMC_License::get_license();
$is_active = SDMC_License::is_active();
?>

<div class="wrap sdmc-license">
    <h1>SD MultiCurrency Pro License</h1>
    
    <div class="sdmc-license-card">
        <?php if ($is_active): ?>
            <div class="sdmc-license-active">
                <span class="dashicons dashicons-yes-alt" style="color:#46b450; font-size:48px; width:48px; height:48px;"></span>
                <h2>License Active</h2>
                <p>Your license is active and valid.</p>
                
                <table class="form-table" style="max-width:400px;">
                    <tr>
                        <th>License Key</th>
                        <td><code><?php echo esc_html(substr($license['key'] ?? '', 0, 20) . '...'); ?></code></td>
                    </tr>
                    <tr>
                        <th>Expires</th>
                        <td><?php echo esc_html($license['expires'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Activated On</th>
                        <td><?php echo esc_html($license['activated_at'] ?? 'N/A'); ?></td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" class="button" id="sdmc-deactivate-license">Deactivate License</button>
                </p>
            </div>
        <?php else: ?>
            <div class="sdmc-license-form">
                <h2>Activate Your License</h2>
                <p>Enter your license key to activate SD MultiCurrency Pro.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">License Key</th>
                        <td>
                            <input type="text" id="sdmc_license_key" class="regular-text" placeholder="SD-MCP-XXXXXXXX-XXXXXXXX-XXXXXXXX">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" class="button button-primary" id="sdmc-activate-license">Activate License</button>
                </p>
                
                <p class="description">
                    Don't have a license key? <a href="https://softdynamix.co.za/plugins/sd-multicurrency-pro" target="_blank">Get one here</a>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.sdmc-license-card {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    max-width: 600px;
    margin-top: 20px;
}
.sdmc-license-active {
    text-align: center;
}
.sdmc-license-active h2 {
    margin: 10px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#sdmc-activate-license').on('click', function() {
        var key = $('#sdmc_license_key').val();
        
        if (!key) {
            alert('Please enter a license key');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'sdmc_activate_license',
            nonce: sdmc_ajax.nonce,
            key: key
        }, function(response) {
            if (response.success) {
                alert('License activated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (response.data.message || 'Invalid license key'));
            }
        });
    });
    
    $('#sdmc-deactivate-license').on('click', function() {
        if (!confirm('Are you sure you want to deactivate the license?')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'sdmc_deactivate_license',
            nonce: sdmc_ajax.nonce
        }, function(response) {
            location.reload();
        });
    });
});
</script>
