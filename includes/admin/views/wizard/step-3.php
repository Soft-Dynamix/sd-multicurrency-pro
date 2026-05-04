<?php
/**
 * Wizard Step 3 - Switcher Style
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<h2>Currency Switcher Style</h2>

<p>Choose how the currency switcher will appear on your site.</p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="sdmc_wizard_step_3">
    <?php wp_nonce_field('sdmc_wizard', 'sdmc_wizard_nonce'); ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">Switcher Style</th>
            <td>
                <label style="display:block; margin-bottom:15px;">
                    <input type="radio" name="switcher_style" value="dropdown" checked>
                    <strong>Dropdown</strong> - Compact dropdown select
                </label>
                <label style="display:block; margin-bottom:15px;">
                    <input type="radio" name="switcher_style" value="buttons">
                    <strong>Buttons</strong> - Horizontal button group
                </label>
                <label style="display:block; margin-bottom:15px;">
                    <input type="radio" name="switcher_style" value="flags">
                    <strong>Flags</strong> - Flag icons for each currency
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">Options</th>
            <td>
                <label>
                    <input type="checkbox" name="checkout_notice" value="1" checked>
                    Show checkout notice about currency conversion
                </label>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <button type="submit" class="button button-primary">Continue →</button>
    </p>
</form>
