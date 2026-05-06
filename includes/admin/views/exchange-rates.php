<?php
/**
 * Exchange Rates Admin View
 * 
 * Displays and manages exchange rates for currency conversion
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current rates
$rates = SDMC_Exchange_Rates::get_rates();
$manual_rates = SDMC_Exchange_Rates::get_manual_rates();
$last_update = SDMC_Exchange_Rates::get_last_update();
$settings = get_option('sdmc_settings', []);
$active_currencies = $settings['active_currencies'] ?? ['ZAR', 'USD', 'GBP', 'EUR'];
$base_currency = $settings['base_currency'] ?? 'ZAR';
?>

<div class="wrap sdmc-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="sdmc-exchange-rates-container">
        <!-- Rate Status Card -->
        <div class="sdmc-card sdmc-rate-status">
            <h2><?php _e('Exchange Rate Status', 'sd-multicurrency-pro'); ?></h2>
            
            <div class="sdmc-status-info">
                <div class="sdmc-status-item">
                    <span class="sdmc-status-label"><?php _e('Base Currency:', 'sd-multicurrency-pro'); ?></span>
                    <span class="sdmc-status-value sdmc-base-currency"><?php echo esc_html($base_currency); ?> (South African Rand)</span>
                </div>
                
                <div class="sdmc-status-item">
                    <span class="sdmc-status-label"><?php _e('Last Updated:', 'sd-multicurrency-pro'); ?></span>
                    <span class="sdmc-status-value">
                        <?php 
                        if ($last_update) {
                            echo esc_html($last_update);
                            echo ' <span class="sdmc-time-ago">(' . esc_html(human_time_diff(strtotime($last_update), current_time('timestamp')) . ' ago') . ')</span>';
                        } else {
                            _e('Never (using default rates)', 'sd-multicurrency-pro');
                        }
                        ?>
                    </span>
                </div>
                
                <div class="sdmc-status-item">
                    <span class="sdmc-status-label"><?php _e('Data Source:', 'sd-multicurrency-pro'); ?></span>
                    <span class="sdmc-status-value">Frankfurter API (free, no key required)</span>
                </div>
            </div>
            
            <div class="sdmc-actions">
                <button type="button" class="button button-primary sdmc-refresh-rates">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh Rates Now', 'sd-multicurrency-pro'); ?>
                </button>
                <span class="sdmc-loading-spinner" style="display: none;">
                    <span class="spinner is-active"></span>
                    <?php _e('Fetching rates...', 'sd-multicurrency-pro'); ?>
                </span>
            </div>
        </div>
        
        <!-- How It Works -->
        <div class="sdmc-card sdmc-how-it-works">
            <h2><?php _e('How Exchange Rates Work', 'sd-multicurrency-pro'); ?></h2>
            <div class="sdmc-info-box">
                <p><strong><?php _e('Your Setup:', 'sd-multicurrency-pro'); ?></strong></p>
                <ul>
                    <li><?php _e('You set manual prices for each currency on your products/courses', 'sd-multicurrency-pro'); ?></li>
                    <li><?php _e('Customers see prices in their selected currency (e.g., $50 USD)', 'sd-multicurrency-pro'); ?></li>
                    <li><?php _e('At checkout, the foreign currency price is converted back to ZAR for Yoco payment', 'sd-multicurrency-pro'); ?></li>
                </ul>
                
                <p><strong><?php _e('Example:', 'sd-multicurrency-pro'); ?></strong></p>
                <div class="sdmc-example">
                    <code>Course price: $50 USD</code><br>
                    <code>Exchange rate: 1 ZAR = 0.054 USD (≈ 18.52 ZAR per USD)</code><br>
                    <code>Customer pays: $50 ÷ 0.054 = R925.93 ZAR</code>
                </div>
            </div>
        </div>
        
        <!-- Current Rates Table -->
        <div class="sdmc-card sdmc-rates-table-card">
            <h2><?php _e('Current Exchange Rates', 'sd-multicurrency-pro'); ?></h2>
            
            <p class="sdmc-description">
                <?php _e('Rates shown are: 1 ZAR = X units of each currency. The inverse rate (how many ZAR for 1 unit of currency) is what determines the conversion at checkout.', 'sd-multicurrency-pro'); ?>
            </p>
            
            <table class="sdmc-rates-table widefat">
                <thead>
                    <tr>
                        <th><?php _e('Currency', 'sd-multicurrency-pro'); ?></th>
                        <th><?php _e('Rate (1 ZAR = X)', 'sd-multicurrency-pro'); ?></th>
                        <th><?php _e('Inverse Rate (1 X = ZAR)', 'sd-multicurrency-pro'); ?></th>
                        <th><?php _e('Manual Override', 'sd-multicurrency-pro'); ?></th>
                        <th><?php _e('Actions', 'sd-multicurrency-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_currencies as $currency) : 
                        $rate = $rates[$currency] ?? null;
                        $inverse_rate = $rate ? (1 / $rate) : null;
                        $is_base = ($currency === $base_currency);
                        $has_manual = isset($manual_rates[$currency]) && is_numeric($manual_rates[$currency]);
                        $manual_value = $manual_rates[$currency] ?? '';
                    ?>
                        <tr class="<?php echo $is_base ? 'sdmc-base-row' : ''; ?>">
                            <td>
                                <strong><?php echo esc_html(SDMC_Currency::get_symbol($currency) . ' ' . $currency); ?></strong>
                                <?php if ($is_base) : ?>
                                    <span class="sdmc-badge"><?php _e('Base', 'sd-multicurrency-pro'); ?></span>
                                <?php endif; ?>
                                <?php if ($has_manual && !$is_base) : ?>
                                    <span class="sdmc-badge sdmc-manual-badge"><?php _e('Manual', 'sd-multicurrency-pro'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_base) : ?>
                                    <code>1.0000</code>
                                <?php elseif ($rate) : ?>
                                    <code><?php echo esc_html(number_format($rate, 6)); ?></code>
                                <?php else : ?>
                                    <span class="sdmc-error">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_base) : ?>
                                    <code>1.0000 ZAR</code>
                                <?php elseif ($inverse_rate) : ?>
                                    <code><?php echo esc_html(number_format($inverse_rate, 4)); ?> ZAR</code>
                                <?php else : ?>
                                    <span class="sdmc-error">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$is_base) : ?>
                                    <input type="number" 
                                           step="0.000001" 
                                           min="0"
                                           class="sdmc-manual-rate-input"
                                           data-currency="<?php echo esc_attr($currency); ?>"
                                           value="<?php echo esc_attr($manual_value); ?>"
                                           placeholder="<?php esc_attr_e('Auto', 'sd-multicurrency-pro'); ?>">
                                <?php else : ?>
                                    <span class="sdmc-na">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$is_base) : ?>
                                    <button type="button" 
                                            class="button sdmc-save-manual-rate"
                                            data-currency="<?php echo esc_attr($currency); ?>">
                                        <?php _e('Set', 'sd-multicurrency-pro'); ?>
                                    </button>
                                    <?php if ($has_manual) : ?>
                                        <button type="button" 
                                                class="button sdmc-clear-manual-rate"
                                                data-currency="<?php echo esc_attr($currency); ?>">
                                            <?php _e('Clear', 'sd-multicurrency-pro'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="sdmc-na">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p class="sdmc-description sdmc-note">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Setting a manual override will use that rate instead of the API rate. Clear to use automatic rates again.', 'sd-multicurrency-pro'); ?>
            </p>
        </div>
        
        <!-- Conversion Calculator -->
        <div class="sdmc-card sdmc-calculator">
            <h2><?php _e('Conversion Calculator', 'sd-multicurrency-pro'); ?></h2>
            
            <div class="sdmc-calc-form">
                <div class="sdmc-calc-row">
                    <label><?php _e('Amount:', 'sd-multicurrency-pro'); ?></label>
                    <input type="number" step="0.01" min="0" id="sdmc-calc-amount" value="100">
                </div>
                
                <div class="sdmc-calc-row">
                    <label><?php _e('From Currency:', 'sd-multicurrency-pro'); ?></label>
                    <select id="sdmc-calc-from">
                        <?php foreach ($active_currencies as $currency) : ?>
                            <option value="<?php echo esc_attr($currency); ?>"><?php echo esc_html($currency); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="sdmc-calc-row">
                    <label><?php _e('To Currency:', 'sd-multicurrency-pro'); ?></label>
                    <select id="sdmc-calc-to">
                        <option value="ZAR" selected><?php _e('ZAR (Base)', 'sd-multicurrency-pro'); ?></option>
                    </select>
                </div>
                
                <button type="button" class="button sdmc-calculate"><?php _e('Calculate', 'sd-multicurrency-pro'); ?></button>
            </div>
            
            <div class="sdmc-calc-result" id="sdmc-calc-result" style="display: none;">
                <strong><?php _e('Result:', 'sd-multicurrency-pro'); ?></strong>
                <span id="sdmc-calc-output"></span>
            </div>
        </div>
        
        <!-- Recent Orders with Currency Conversion -->
        <div class="sdmc-card sdmc-recent-orders">
            <h2><?php _e('Recent Currency Conversions', 'sd-multicurrency-pro'); ?></h2>
            
            <?php
            // Get recent orders with currency conversion
            $args = [
                'limit' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => [
                    [
                        'key' => '_sdmc_customer_currency',
                        'compare' => 'EXISTS',
                    ],
                ],
            ];
            
            $orders = wc_get_orders($args);
            
            if ($orders) : ?>
                <table class="sdmc-orders-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Order', 'sd-multicurrency-pro'); ?></th>
                            <th><?php _e('Date', 'sd-multicurrency-pro'); ?></th>
                            <th><?php _e('Customer Currency', 'sd-multicurrency-pro'); ?></th>
                            <th><?php _e('Exchange Rate', 'sd-multicurrency-pro'); ?></th>
                            <th><?php _e('ZAR Charged', 'sd-multicurrency-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order) : 
                            $customer_currency = $order->get_meta('_sdmc_customer_currency');
                            $exchange_rate = $order->get_meta('_sdmc_exchange_rate');
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($order->get_edit_order_url()); ?>">
                                        #<?php echo esc_html($order->get_order_number()); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($order->get_date_created()->format('Y-m-d H:i')); ?></td>
                                <td><?php echo esc_html($customer_currency); ?></td>
                                <td>
                                    <?php 
                                    if ($exchange_rate) {
                                        echo esc_html('1 ZAR = ' . number_format($exchange_rate, 4) . ' ' . $customer_currency);
                                    }
                                    ?>
                                </td>
                                <td><strong>R<?php echo esc_html(number_format($order->get_total(), 2)); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="sdmc-no-data"><?php _e('No currency conversions yet. Orders will appear here when customers purchase in a foreign currency.', 'sd-multicurrency-pro'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.sdmc-admin-wrap {
    max-width: 1200px;
    margin: 20px auto;
}

.sdmc-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.sdmc-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 16px;
}

.sdmc-status-info {
    margin: 15px 0;
}

.sdmc-status-item {
    display: flex;
    gap: 10px;
    margin-bottom: 8px;
}

.sdmc-status-label {
    font-weight: 600;
    min-width: 140px;
    color: #555;
}

.sdmc-base-currency {
    font-weight: 600;
    color: #0073aa;
}

.sdmc-time-ago {
    color: #666;
    font-style: italic;
}

.sdmc-actions {
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sdmc-actions .dashicons {
    margin-right: 5px;
}

.sdmc-info-box {
    background: #f6f7f7;
    padding: 15px;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.sdmc-info-box ul {
    margin: 10px 0;
    padding-left: 20px;
}

.sdmc-info-box li {
    margin-bottom: 5px;
}

.sdmc-example {
    background: #fff;
    padding: 10px;
    border-radius: 4px;
    margin-top: 10px;
}

.sdmc-example code {
    display: block;
    margin: 3px 0;
    color: #0073aa;
}

.sdmc-rates-table {
    margin-top: 15px;
}

.sdmc-rates-table th {
    background: #f6f7f7;
}

.sdmc-rates-table td {
    vertical-align: middle;
}

.sdmc-base-row {
    background: #f0f6fc;
}

.sdmc-badge {
    display: inline-block;
    background: #0073aa;
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 8px;
    text-transform: uppercase;
}

.sdmc-manual-badge {
    background: #ff9800;
}

.sdmc-manual-rate-input {
    width: 120px;
}

.sdmc-description {
    color: #666;
    font-style: italic;
}

.sdmc-note {
    background: #fff9e6;
    padding: 10px 15px;
    border-radius: 4px;
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sdmc-calc-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    align-items: end;
    margin-bottom: 15px;
}

.sdmc-calc-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.sdmc-calc-row input,
.sdmc-calc-row select {
    width: 100%;
}

.sdmc-calc-result {
    background: #e7f7ff;
    padding: 15px;
    border-radius: 4px;
    font-size: 16px;
}

.sdmc-no-data {
    color: #666;
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Refresh rates
    $('.sdmc-refresh-rates').on('click', function() {
        var $btn = $(this);
        var $spinner = $('.sdmc-loading-spinner');
        
        $btn.prop('disabled', true);
        $spinner.show();
        
        $.ajax({
            url: sdmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sdmc_refresh_rates',
                nonce: sdmc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to refresh rates');
                }
            },
            error: function() {
                alert('Error refreshing rates');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.hide();
            }
        });
    });
    
    // Save manual rate
    $('.sdmc-save-manual-rate').on('click', function() {
        var $btn = $(this);
        var currency = $btn.data('currency');
        var rate = $('.sdmc-manual-rate-input[data-currency="' + currency + '"]').val();
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: sdmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sdmc_update_manual_rate',
                nonce: sdmc_ajax.nonce,
                currency: currency,
                rate: rate
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to update rate');
                }
            },
            error: function() {
                alert('Error updating rate');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Clear manual rate
    $('.sdmc-clear-manual-rate').on('click', function() {
        var $btn = $(this);
        var currency = $btn.data('currency');
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: sdmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sdmc_update_manual_rate',
                nonce: sdmc_ajax.nonce,
                currency: currency,
                rate: ''
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to clear rate');
                }
            },
            error: function() {
                alert('Error clearing rate');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Conversion calculator
    $('.sdmc-calculate').on('click', function() {
        var amount = parseFloat($('#sdmc-calc-amount').val());
        var fromCurrency = $('#sdmc-calc-from').val();
        var toCurrency = $('#sdmc-calc-to').val();
        
        if (isNaN(amount) || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        // Get rates from the table
        var rates = {};
        <?php foreach ($active_currencies as $currency) : ?>
            rates['<?php echo esc_js($currency); ?>'] = <?php echo esc_js($rates[$currency] ?? 1); ?>;
        <?php endforeach; ?>
        
        var result;
        var symbol = 'R';
        
        if (fromCurrency === 'ZAR') {
            // Converting from ZAR to another currency
            result = amount * rates[toCurrency];
            symbol = '<?php echo esc_js(SDMC_Currency::get_symbol("USD")); ?>'; // Will be updated based on target
        } else if (toCurrency === 'ZAR') {
            // Converting to ZAR (our main use case)
            result = amount / rates[fromCurrency];
        } else {
            // Converting between two foreign currencies (via ZAR)
            var zarAmount = amount / rates[fromCurrency];
            result = zarAmount * rates[toCurrency];
        }
        
        $('#sdmc-calc-output').text(
            '<?php echo esc_js(SDMC_Currency::get_symbol("ZAR")); ?>' + result.toFixed(2) + ' ZAR'
        );
        $('#sdmc-calc-result').show();
    });
});
</script>
