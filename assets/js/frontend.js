/**
 * SD MultiCurrency Pro - Frontend Scripts
 */

(function($) {
    'use strict';
    
    // Document ready
    $(document).ready(function() {
        initSwitcher();
    });
    
    /**
     * Initialize currency switcher
     */
    function initSwitcher() {
        // Dropdown change
        $(document).on('change', '.sdmc-currency-select', function() {
            var currency = $(this).val();
            setCurrency(currency);
        });
        
        // Button click
        $(document).on('click', '.sdmc-currency-btn', function(e) {
            e.preventDefault();
            var currency = $(this).data('currency');
            setCurrency(currency);
        });
        
        // Flag click
        $(document).on('click', '.sdmc-currency-flag', function(e) {
            e.preventDefault();
            var currency = $(this).data('currency');
            setCurrency(currency);
        });
    }
    
    /**
     * Set currency via AJAX
     */
    function setCurrency(currency) {
        if (!currency) {
            console.error('No currency provided');
            return;
        }
        
        // Show loading state
        $('.sdmc-currency-btn, .sdmc-currency-flag').prop('disabled', true);
        $('.sdmc-currency-select').prop('disabled', true);
        
        $.ajax({
            url: sdmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sdmc_switch_currency',
                nonce: sdmc_ajax.nonce,
                currency: currency
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    updateSwitcherUI(currency);
                    
                    // Reload page to reflect new prices
                    location.reload();
                } else {
                    console.error('Failed to set currency:', response.data);
                    alert('Failed to switch currency. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('Connection error. Please try again.');
            },
            complete: function() {
                $('.sdmc-currency-btn, .sdmc-currency-flag').prop('disabled', false);
                $('.sdmc-currency-select').prop('disabled', false);
            }
        });
    }
    
    /**
     * Update switcher UI
     */
    function updateSwitcherUI(currency) {
        // Update dropdown
        $('.sdmc-currency-select').val(currency);
        
        // Update buttons
        $('.sdmc-currency-btn').removeClass('sdmc-active');
        $('.sdmc-currency-btn[data-currency="' + currency + '"]').addClass('sdmc-active');
        
        // Update flags
        $('.sdmc-currency-flag').removeClass('sdmc-active');
        $('.sdmc-currency-flag[data-currency="' + currency + '"]').addClass('sdmc-active');
        
        // Update body class
        $('body').removeClass('sdmc-currency-usd sdmc-currency-gbp sdmc-currency-eur sdmc-currency-zar sdmc-currency-aud sdmc-currency-cad sdmc-currency-nzd');
        $('body').addClass('sdmc-currency-' + currency.toLowerCase());
    }
    
    /**
     * Format price
     */
    function formatPrice(price, currency) {
        var symbols = {
            'ZAR': 'R',
            'USD': '$',
            'GBP': '£',
            'EUR': '€',
            'AUD': 'A$',
            'CAD': 'C$',
            'NZD': 'NZ$'
        };
        
        var symbol = symbols[currency] || currency;
        var formatted = parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        
        return symbol + formatted;
    }
    
    // Global function for inline onchange handlers
    window.sdmcSwitchCurrency = function(currency) {
        setCurrency(currency);
    };
    
    // Expose to global
    window.SDMC_Frontend = {
        setCurrency: setCurrency,
        formatPrice: formatPrice,
        updateSwitcherUI: updateSwitcherUI
    };
    
})(jQuery);
