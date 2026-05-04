<?php
/**
 * Wizard Step 2 - Currencies
 */

if (!defined('ABSPATH')) {
    exit;
}

$available_currencies = SDMC_Helpers::get_available_currencies();
?>

<h2>Configure Currencies</h2>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="sdmc_wizard_step_2">
    <?php wp_nonce_field('sdmc_wizard', 'sdmc_wizard_nonce'); ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">Base Currency</th>
            <td>
                <select name="base_currency" required>
                    <?php foreach ($available_currencies as $code => $data): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($code, 'ZAR'); ?>>
                            <?php echo esc_html($code . ' - ' . $data['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">This is the currency used for payment processing (e.g., ZAR for Yoco).</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Active Currencies</th>
            <td>
                <fieldset>
                    <?php foreach ($available_currencies as $code => $data): ?>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="active_currencies[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, ['ZAR', 'USD', 'GBP'])); ?>>
                            <?php echo esc_html($code . ' - ' . $data['name'] . ' (' . $data['symbol'] . ')'); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <button type="submit" class="button button-primary">Continue →</button>
    </p>
</form>
