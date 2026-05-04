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
        $('#sdmc-switch').on('change', function() {
            var currency = $(this).val();
            setCurrency(currency);
        });
        
        // Button click
        $('.sdmc-btn').on('click', function() {
            var currency = $(this).data('currency');
            setCurrency(currency);
        });
        
        // Flag click
        $('.sdmc-flag-btn').on('click', function() {
            var currency = $(this).data('currency');
            setCurrency(currency);
        });
    }
    
    /**
     * Set currency via AJAX
     */
    function setCurrency(currency) {
        // Check if cart has items (optional lock)
        // Uncomment below to prevent switching with cart items
        /*
        if (typeof wc_cart_fragments_params !== 'undefined') {
            if ($('.woocommerce-cart-form').length) {
                alert('Cannot change currency while items are in cart');
                return;
            }
        }
        */
        
        // Show loading state
        $('.sdmc-btn, .sdmc-flag-btn').prop('disabled', true);
        $('#sdmc-switch').prop('disabled', true);
        
        $.ajax({
            url: sdmc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sdmc_set_currency',
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
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            },
            complete: function() {
                $('.sdmc-btn, .sdmc-flag-btn').prop('disabled', false);
                $('#sdmc-switch').prop('disabled', false);
            }
        });
    }
    
    /**
     * Update switcher UI
     */
    function updateSwitcherUI(currency) {
        // Update dropdown
        $('#sdmc-switch').val(currency);
        
        // Update buttons
        $('.sdmc-btn').removeClass('sdmc-btn-active');
        $('.sdmc-btn[data-currency="' + currency + '"]').addClass('sdmc-btn-active');
        
        // Update flags
        $('.sdmc-flag-btn').removeClass('sdmc-flag-active');
        $('.sdmc-flag-btn[data-currency="' + currency + '"]').addClass('sdmc-flag-active');
        
        // Update body class
        $('body').removeClass('sdmc-currency-usd sdmc-currency-gbp sdmc-currency-eur sdmc-currency-zar');
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
            'EUR': '€'
        };
        
        var symbol = symbols[currency] || currency;
        var formatted = parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        
        return symbol + formatted;
    }
    
    // Expose to global
    window.SDMC_Frontend = {
        setCurrency: setCurrency,
        formatPrice: formatPrice,
        updateSwitcherUI: updateSwitcherUI
    };
    
})(jQuery);
