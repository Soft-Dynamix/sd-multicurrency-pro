<?php
/**
 * Frontend Currency Switcher Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$style = $atts['style'] ?? 'dropdown';
$show_flags = $atts['show_flags'] === 'true';
$show_symbols = $atts['show_symbols'] === 'true';
?>

<div class="sdmc-switcher" data-style="<?php echo esc_attr($style); ?>">
    <?php if ($style === 'dropdown'): ?>
        <select id="sdmc-switch" class="sdmc-select" aria-label="<?php _e('Select currency', 'sd-multicurrency-pro'); ?>">
            <?php foreach ($active_currencies as $currency): ?>
                <option value="<?php echo esc_attr($currency); ?>" <?php selected($current_currency, $currency); ?>>
                    <?php 
                    $label = '';
                    if ($show_flags) {
                        $label .= SDMC_Currency::get_flag($currency) . ' ';
                    }
                    $label .= $currency;
                    if ($show_symbols) {
                        $label .= ' (' . SDMC_Currency::get_symbol($currency) . ')';
                    }
                    echo esc_html($label);
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        
    <?php elseif ($style === 'buttons'): ?>
        <div class="sdmc-buttons">
            <?php foreach ($active_currencies as $currency): ?>
                <button type="button" 
                        class="sdmc-btn <?php echo $current_currency === $currency ? 'sdmc-btn-active' : ''; ?>"
                        data-currency="<?php echo esc_attr($currency); ?>">
                    <?php if ($show_flags): ?>
                        <span class="sdmc-flag"><?php echo SDMC_Currency::get_flag($currency); ?></span>
                    <?php endif; ?>
                    <span class="sdmc-code"><?php echo esc_html($currency); ?></span>
                    <?php if ($show_symbols): ?>
                        <span class="sdmc-symbol"><?php echo esc_html(SDMC_Currency::get_symbol($currency)); ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        
    <?php elseif ($style === 'flags'): ?>
        <div class="sdmc-flags">
            <?php foreach ($active_currencies as $currency): ?>
                <button type="button" 
                        class="sdmc-flag-btn <?php echo $current_currency === $currency ? 'sdmc-flag-active' : ''; ?>"
                        data-currency="<?php echo esc_attr($currency); ?>"
                        title="<?php echo esc_attr($currency . ' - ' . SDMC_Currency::get_currency_name($currency)); ?>">
                    <?php echo SDMC_Currency::get_flag($currency); ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <input type="hidden" name="sdmc_currency_nonce" value="<?php echo wp_create_nonce('sdmc_currency'); ?>">
</div>

<style>
.sdmc-switcher {
    display: inline-block;
}

.sdmc-select {
    appearance: none;
    -webkit-appearance: none;
    background: #fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="%236b7280" stroke-width="2"><polyline points="6,9 12,15 18,9"></polyline></svg>') no-repeat right 10px center;
    border: 1px solid #e5e7eb;
    padding: 10px 35px 10px 15px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 120px;
}

.sdmc-select:hover {
    border-color: #2563EB;
}

.sdmc-select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.sdmc-buttons {
    display: flex;
    gap: 8px;
}

.sdmc-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.sdmc-btn:hover {
    background: #EFF6FF;
    border-color: #2563EB;
}

.sdmc-btn-active {
    background: #2563EB;
    border-color: #2563EB;
    color: #fff;
}

.sdmc-btn-active .sdmc-symbol {
    color: #fff;
}

.sdmc-flag {
    font-size: 18px;
}

.sdmc-symbol {
    color: #6b7280;
    font-weight: 500;
}

.sdmc-flags {
    display: flex;
    gap: 5px;
}

.sdmc-flag-btn {
    padding: 8px;
    background: #f9fafb;
    border: 2px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    font-size: 24px;
    transition: all 0.2s;
}

.sdmc-flag-btn:hover {
    background: #EFF6FF;
    transform: scale(1.1);
}

.sdmc-flag-active {
    border-color: #2563EB;
    background: #EFF6FF;
}

.sdmc-switcher-locked {
    padding: 10px 15px;
    background: #f3f4f6;
    border-radius: 8px;
    font-size: 12px;
}
</style>
