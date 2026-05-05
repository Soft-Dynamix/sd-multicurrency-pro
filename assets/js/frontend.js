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
        if (currentCurrency !== baseCurrency && typeof sdmc_ajax !== 'undefined') {
            setTimeout(function() {
                loadAndApplyPrices();
            }, 100);
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
     * Load prices from server and apply to page
     */
    function loadAndApplyPrices() {
        // Find all course IDs on the page
        var courseIds = [];
        var coursePriceMap = {}; // Map of course ID to price element
        
        // Method 1: Look for data-course-id attributes
        $('[data-course-id]').each(function() {
            var id = $(this).data('course-id');
            if (id && courseIds.indexOf(id) === -1) {
                courseIds.push(id);
            }
        });
        
        // Method 2: Look for Tutor LMS course cards/loops
        $('.tutor-course-loop, .tutor-course-card, article.courses').each(function() {
            var $el = $(this);
            var id = $el.data('id') || $el.attr('id');
            if (id) {
                id = String(id).replace(/\D/g, '');
                if (id && courseIds.indexOf(parseInt(id)) === -1) {
                    courseIds.push(parseInt(id));
                    coursePriceMap[id] = $el;
                }
            }
        });
        
        // Method 3: Look for WooCommerce products
        $('.product, .wc-block-product').each(function() {
            var $el = $(this);
            var id = $el.data('id') || $el.attr('id');
            if (id) {
                id = String(id).replace(/\D/g, '');
                if (id && courseIds.indexOf(parseInt(id)) === -1) {
                    courseIds.push(parseInt(id));
                }
            }
        });
        
        // Method 4: Extract from page content if available
        if (typeof sdmc_course_ids !== 'undefined' && sdmc_course_ids.length > 0) {
            sdmc_course_ids.forEach(function(id) {
                if (courseIds.indexOf(id) === -1) {
                    courseIds.push(id);
                }
            });
        }
        
        // If we found course IDs, fetch their prices
        if (courseIds.length > 0) {
            $.ajax({
                url: sdmc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sdmc_get_course_prices',
                    nonce: sdmc_ajax.nonce,
                    currency: currentCurrency,
                    course_ids: courseIds
                },
                success: function(response) {
                    if (response.success && response.data.prices) {
                        applyPrices(response.data.prices, response.data.symbol);
                    }
                }
            });
        }
    }
    
    /**
     * Apply prices to DOM elements
     */
    function applyPrices(prices, symbol) {
        var baseSymbol = symbols[baseCurrency] || 'R';
        
        $.each(prices, function(courseId, priceData) {
            // Update elements with data-course-id
            $('[data-course-id="' + courseId + '"]').each(function() {
                $(this).text(priceData.formatted);
            });
            
            // Update Tutor LMS price elements within course cards
            if (priceData.formatted) {
                // Find course container
                var $container = $('[data-id="' + courseId + '"]');
                if ($container.length === 0) {
                    $container = $('.tutor-course-card[data-id="' + courseId + '"]');
                }
                if ($container.length === 0) {
                    $container = $('#course-' + courseId);
                }
                
                if ($container.length) {
                    // Update price elements inside
                    $container.find('.tutor-course-price, .price, .tutor-price, .woocommerce-Price-amount').each(function() {
                        $(this).text(priceData.formatted);
                    });
                }
            }
        });
        
        // Fallback: Replace all R symbols with current currency symbol
        // This catches any prices we missed
        if (symbol !== baseSymbol) {
            $('.tutor-course-price, .price, .tutor-price, .woocommerce-Price-amount').each(function() {
                var $el = $(this);
                var text = $el.text();
                var newText = text.replace(new RegExp(baseSymbol + '\\s*'), symbol);
                if (text !== newText) {
                    $el.text(newText);
                }
            });
        }
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
        loadAndApplyPrices: loadAndApplyPrices
    };
    
})(jQuery);
