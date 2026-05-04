<?php
/**
 * Wizard Step 4 - Complete
 */

if (!defined('ABSPATH')) {
    exit;
}

// Mark wizard as complete
delete_option('sdmc_run_wizard');
?>

<div style="text-align:center; padding:40px 0;">
    <span class="dashicons dashicons-yes-alt" style="color:#46b450; font-size:64px; width:64px; height:64px;"></span>
    
    <h2>Setup Complete!</h2>
    
    <p>Your SD MultiCurrency Pro plugin is now configured and ready to use.</p>
    
    <h3>Next Steps:</h3>
    
    <ol style="text-align:left; max-width:400px; margin:20px auto;">
        <li>
            <strong>Add prices to products</strong><br>
            <span class="description">Edit products to set prices for each currency</span>
        </li>
        <li style="margin-top:15px;">
            <strong>Add the currency switcher</strong><br>
            <span class="description">Use shortcode: <code>[sd_currency_switcher]</code></span>
        </li>
        <li style="margin-top:15px;">
            <strong>Configure settings</strong><br>
            <span class="description">Fine-tune settings in the admin menu</span>
        </li>
    </ol>
    
    <p style="margin-top:30px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=sd-multicurrency-pro')); ?>" class="button button-primary button-hero">
            Go to Dashboard
        </a>
    </p>
</div>
