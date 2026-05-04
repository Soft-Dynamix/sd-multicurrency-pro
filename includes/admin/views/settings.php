<?php
/**
 * Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = SDMC_Settings::get_settings();
$available_currencies = SDMC_Helpers::get_available_currencies();
$active_currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
?>

<div class="wrap sdmc-settings">
    <h1>SD MultiCurrency Pro Settings</h1>
    
    <form method="post" id="sdmc-settings-form">
        <?php wp_nonce_field('sdmc_nonce', 'sdmc_nonce'); ?>
        
        <table class="form-table">
            <!-- Base Currency -->
            <tr>
                <th scope="row">Base Currency</th>
                <td>
                    <select name="sdmc_settings[base_currency]" id="sdmc_base_currency">
                        <?php foreach ($available_currencies as $code => $data): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['base_currency'] ?? 'ZAR', $code); ?>>
                                <?php echo esc_html($code . ' - ' . $data['name'] . ' (' . $data['symbol'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">The currency used for checkout processing (e.g., ZAR for Yoco).</p>
                </td>
            </tr>
            
            <!-- Active Currencies -->
            <tr>
                <th scope="row">Active Currencies</th>
                <td>
                    <fieldset>
                        <?php foreach ($available_currencies as $code => $data): ?>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="checkbox" name="sdmc_settings[active_currencies][]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $active_currencies)); ?>>
                                <?php echo esc_html($code . ' - ' . $data['name'] . ' (' . $data['symbol'] . ')'); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>
            
            <!-- Switcher Style -->
            <tr>
                <th scope="row">Switcher Style</th>
                <td>
                    <select name="sdmc_settings[switcher_style]" id="sdmc_switcher_style">
                        <option value="dropdown" <?php selected($settings['switcher_style'] ?? 'dropdown', 'dropdown'); ?>>Dropdown</option>
                        <option value="buttons" <?php selected($settings['switcher_style'] ?? 'dropdown', 'buttons'); ?>>Buttons</option>
                        <option value="flags" <?php selected($settings['switcher_style'] ?? 'dropdown', 'flags'); ?>>Flags</option>
                    </select>
                </td>
            </tr>
            
            <!-- Checkout Notice -->
            <tr>
                <th scope="row">Checkout Notice</th>
                <td>
                    <label>
                        <input type="checkbox" name="sdmc_settings[checkout_notice]" value="1" <?php checked(!empty($settings['checkout_notice'])); ?>>
                        Show checkout notice about currency conversion
                    </label>
                </td>
            </tr>
            
            <!-- Show Flag -->
            <tr>
                <th scope="row">Show Flags</th>
                <td>
                    <label>
                        <input type="checkbox" name="sdmc_settings[show_flag]" value="1" <?php checked(!empty($settings['show_flag'])); ?>>
                        Show country flags in switcher
                    </label>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary">Save Settings</button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#sdmc-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var data = $(this).serialize();
        
        $.post(ajaxurl, {
            action: 'sdmc_save_settings',
            nonce: sdmc_ajax.nonce,
            settings: {
                base_currency: $('#sdmc_base_currency').val(),
                active_currencies: $('input[name="sdmc_settings[active_currencies][]"]:checked').map(function() { return $(this).val(); }).get(),
                switcher_style: $('#sdmc_switcher_style').val(),
                checkout_notice: $('input[name="sdmc_settings[checkout_notice]"]').is(':checked') ? 1 : 0,
                show_flag: $('input[name="sdmc_settings[show_flag]"]').is(':checked') ? 1 : 0
            }
        }, function(response) {
            if (response.success) {
                alert('Settings saved!');
            } else {
                alert('Error: ' + (response.data.message || 'Unknown error'));
            }
        });
    });
});
</script>
