/**
 * SD MultiCurrency Pro - Frontend Scripts
 */

(function($) {
    'use strict';
    
    // Currency data from PHP
    var currentCurrency = (typeof sdmc_ajax !== 'undefined' && sdmc_ajax.current_currency) ? sdmc_ajax.current_currency : 'ZAR';
    var baseCurrency = 'ZAR';
    var symbols = {
        'ZAR': 'R',
        'USD': '$',
        'GBP': '£',
        'EUR': '€',
        'AUD': 'A$',
        'CAD': 'C$',
        'NZD': 'NZ$'
    };
    
    // Document ready
    $(document).ready(function() {
        initSwitcher();
        
        // Apply price conversion on page load
        if (currentCurrency !== baseCurrency) {
            applyPriceConversion();
        }
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
     * Apply price conversion via AJAX
     */
    function applyPriceConversion() {
        // Get all course/product prices on the page
        var priceElements = [];
        
        // Tutor LMS price elements
        $('.tutor-course-price, .tutor-price, .price').each(function() {
            var $el = $(this);
            var text = $el.text().trim();
            
            // Check if it contains a price pattern (R followed by number)
            if (text.match(/R\s*[\d,]+\.?\d*/)) {
                priceElements.push({
                    element: $el,
                    originalText: text
                });
            }
        });
        
        // WooCommerce price elements
        $('.woocommerce-Price-amount').each(function() {
            var $el = $(this);
            var text = $el.text().trim();
            
            if (text.match(/R\s*[\d,]+\.?\d*/)) {
                priceElements.push({
                    element: $el,
                    originalText: text
                });
            }
        });
        
        // If we have price elements, request conversion
        if (priceElements.length > 0 && typeof sdmc_ajax !== 'undefined') {
            // Request converted prices from server
            $.ajax({
                url: sdmc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sdmc_get_converted_prices',
                    nonce: sdmc_ajax.nonce,
                    currency: currentCurrency
                },
                success: function(response) {
                    if (response.success && response.data.prices) {
                        // Update prices with server data
                        updatePricesFromServer(priceElements, response.data.prices, response.data.symbol);
                    }
                }
            });
        }
    }
    
    /**
     * Update prices from server response
     */
    function updatePricesFromServer(elements, prices, symbol) {
        elements.forEach(function(item) {
            var $el = item.element;
            var text = item.originalText;
            
            // Extract price from text
            var match = text.match(/R\s*([\d,]+\.?\d*)/);
            if (match) {
                var originalPrice = parseFloat(match[1].replace(/,/g, ''));
                
                // Format new price
                var formattedPrice = symbol + ' ' + originalPrice.toLocaleString('en-ZA', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                // Replace in text
                var newText = text.replace(/R\s*[\d,]+\.?\d*/, formattedPrice);
                $el.text(newText);
            }
        });
    }
    
    /**
     * Format price
     */
    function formatPrice(price, currency) {
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
        updateSwitcherUI: updateSwitcherUI,
        applyPriceConversion: applyPriceConversion
    };
    
})(jQuery);
