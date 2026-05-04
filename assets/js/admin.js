/**
 * SD MultiCurrency Pro - Admin Scripts
 */

(function($) {
    'use strict';
    
    // Document ready
    $(document).ready(function() {
        initTabs();
        initRadioCards();
        initAjaxHandlers();
        initProductFields();
    });
    
    /**
     * Initialize tabs
     */
    function initTabs() {
        $('.sdmc-tab').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            
            // Update active tab
            $('.sdmc-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show target content
            $('.sdmc-tab-content').removeClass('active');
            $(target).addClass('active');
            
            // Update URL hash
            history.pushState(null, null, target);
        });
        
        // Check for hash in URL
        var hash = window.location.hash;
        if (hash) {
            $('.sdmc-tab[href="' + hash + '"]').trigger('click');
        }
    }
    
    /**
     * Initialize radio cards
     */
    function initRadioCards() {
        $('.sdmc-radio-card').on('click', function() {
            var $radio = $(this).find('input[type="radio"]');
            $radio.prop('checked', true);
            
            // Update visual state
            $('.sdmc-radio-card').removeClass('sdmc-selected');
            $(this).addClass('sdmc-selected');
        });
        
        // Check initial state
        $('.sdmc-radio-card input:checked').each(function() {
            $(this).closest('.sdmc-radio-card').addClass('sdmc-selected');
        });
    }
    
    /**
     * Initialize AJAX handlers
     */
    function initAjaxHandlers() {
        // License activation
        $('#sdmc-license-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var key = $('#license_key').val();
            
            if (!key) {
                alert('Please enter a license key');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="sdmc-loading"></span> Activating...');
            
            $.ajax({
                url: sdmc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sdmc_activate_license',
                    nonce: sdmc_ajax.nonce,
                    key: key
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice('error', response.data.message || 'Activation failed');
                    }
                },
                error: function() {
                    showNotice('error', 'Connection error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Activate');
                }
            });
        });
        
        // License deactivation
        $('#sdmc-deactivate-license').on('click', function() {
            if (!confirm('Are you sure you want to deactivate this license?')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true);
            
            $.ajax({
                url: sdmc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sdmc_deactivate_license',
                    nonce: sdmc_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // License check
        $('#sdmc-check-license').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Checking...');
            
            $.ajax({
                url: sdmc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sdmc_check_license',
                    nonce: sdmc_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        if (data.valid) {
                            showNotice('success', 'License is valid. Expires: ' + (data.expires || 'N/A'));
                        } else {
                            showNotice('warning', 'License invalid: ' + (data.message || 'Unknown error'));
                        }
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Check Status');
                }
            });
        });
    }
    
    /**
     * Initialize product fields
     */
    function initProductFields() {
        // WooCommerce product tabs
        $('.product_data_tabs .sdmc_multicurrency_tab a').on('click', function() {
            $('#sdmc_multicurrency_data').show();
        });
        
        // Variable product fields sync
        $('input[name^="_sd_price_"][name$="_panel"]').on('change', function() {
            var name = $(this).attr('name').replace('_panel', '');
            $('input[name="' + name + '"]').val($(this).val());
        });
    }
    
    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var $notice = $('<div class="sdmc-notice sdmc-notice-' + type + '">' +
                       '<p>' + message + '</p>' +
                       '</div>');
        
        $('.wrap h1').first().after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Expose to global
    window.SDMC_Admin = {
        showNotice: showNotice
    };
    
})(jQuery);
