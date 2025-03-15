(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 */
    
    $(document).ready(function() {
        /**
         * Create Staging Page
         */
        
        // Toggle advanced options
        $('.toggle-advanced').on('click', function(e) {
            e.preventDefault();
            
            const advancedOptions = $('.advanced-options');
            
            if (advancedOptions.is(':visible')) {
                advancedOptions.slideUp();
                $(this).text(wp_easy_staging_i18n.show_advanced);
            } else {
                advancedOptions.slideDown();
                $(this).text(wp_easy_staging_i18n.hide_advanced);
            }
        });
        
        // Create staging form submission
        $('#wp-easy-staging-create-form').on('submit', function(e) {
            e.preventDefault();
            
            // Show loading overlay
            showLoadingOverlay(wp_easy_staging_i18n.creating_staging);
            
            // Submit form via AJAX
            $.ajax({
                url: wp_easy_staging_ajax.ajax_url,
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    hideLoadingOverlay();
                    
                    if (response.success) {
                        window.location.href = wp_easy_staging_i18n.admin_url + '?page=wp-easy-staging&created=1';
                    } else {
                        alert(response.data.message || wp_easy_staging_i18n.error_occurred);
                    }
                },
                error: function() {
                    hideLoadingOverlay();
                    alert(wp_easy_staging_i18n.error_occurred);
                }
            });
        });
        
        /**
         * Dashboard Page
         */
        
        // Delete staging site
        $('.wp-easy-staging-delete').on('click', function(e) {
            e.preventDefault();
            
            if (confirm(wp_easy_staging_i18n.confirm_delete)) {
                const stagingId = $(this).data('id');
                const $button = $(this);
                
                // Disable the button
                $button.prop('disabled', true);
                
                // Show loading overlay
                showLoadingOverlay(wp_easy_staging_i18n.deleting_staging);
                
                $.ajax({
                    url: wp_easy_staging_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wp_easy_staging_delete_staging',
                        id: stagingId,
                        nonce: wp_easy_staging_ajax.nonce
                    },
                    success: function(response) {
                        hideLoadingOverlay();
                        
                        if (response.success) {
                            // Show success message
                            const $notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                            $('.wrap h1').after($notice);
                            
                            // Remove the staging site row
                            $button.closest('tr').fadeOut(400, function() {
                                $(this).remove();
                                // If no more staging sites, reload to show empty state
                                if ($('.wp-easy-staging-sites-table tbody tr').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            // Re-enable the button
                            $button.prop('disabled', false);
                            alert(response.data.message || wp_easy_staging_i18n.error_occurred);
                        }
                    },
                    error: function() {
                        hideLoadingOverlay();
                        // Re-enable the button
                        $button.prop('disabled', false);
                        alert(wp_easy_staging_i18n.error_occurred);
                    }
                });
            }
        });
        
        /**
         * Push Changes Page
         */
        
        // Select all database changes
        $('#select-all-db').on('change', function() {
            const isChecked = $(this).prop('checked');
            $(this).closest('table').find('tbody input[type="checkbox"][name="selected_items[]"][value^="db:"]').prop('checked', isChecked);
        });
        
        // Select all file changes
        $('#select-all-files').on('change', function() {
            const isChecked = $(this).prop('checked');
            $(this).closest('table').find('tbody input[type="checkbox"][name="selected_items[]"][value^="file:"]').prop('checked', isChecked);
        });
        
        // Custom merge toggle
        $('input[name^="resolution"][value="custom"]').on('change', function() {
            if ($(this).is(':checked')) {
                $(this).closest('.wp-easy-staging-resolution-options').find('.custom-merge-container').slideDown();
            }
        });
        
        $('input[name^="resolution"][value!="custom"]').on('change', function() {
            if ($(this).is(':checked')) {
                $(this).closest('.wp-easy-staging-resolution-options').find('.custom-merge-container').slideUp();
            }
        });
        
        // Push changes form submission
        $('#wp-easy-staging-push-form').on('submit', function(e) {
            e.preventDefault();
            
            // Check if anything is selected
            const selectedItems = $('input[name="selected_items[]"]:checked').length;
            if (selectedItems === 0) {
                alert(wp_easy_staging_i18n.no_items_selected);
                return;
            }
            
            // Confirm push
            if (confirm(wp_easy_staging_i18n.confirm_push)) {
                // Show loading overlay
                showLoadingOverlay(wp_easy_staging_i18n.pushing_changes);
                
                // Submit form via AJAX
                $.ajax({
                    url: wp_easy_staging_ajax.ajax_url,
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        hideLoadingOverlay();
                        
                        if (response.success) {
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                window.location.href = wp_easy_staging_i18n.admin_url + '?page=wp-easy-staging&pushed=1';
                            }
                        } else {
                            alert(response.data.message || wp_easy_staging_i18n.error_occurred);
                        }
                    },
                    error: function() {
                        hideLoadingOverlay();
                        alert(wp_easy_staging_i18n.error_occurred);
                    }
                });
            }
        });
        
        // Resolve conflicts form submission
        $('#wp-easy-staging-resolve-conflicts').on('submit', function(e) {
            e.preventDefault();
            
            // Show loading overlay
            showLoadingOverlay(wp_easy_staging_i18n.resolving_conflicts);
            
            // Submit form via AJAX
            $.ajax({
                url: wp_easy_staging_ajax.ajax_url,
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    hideLoadingOverlay();
                    
                    if (response.success) {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.href = wp_easy_staging_i18n.admin_url + '?page=wp-easy-staging-push';
                        }
                    } else {
                        alert(response.data.message || wp_easy_staging_i18n.error_occurred);
                    }
                },
                error: function() {
                    hideLoadingOverlay();
                    alert(wp_easy_staging_i18n.error_occurred);
                }
            });
        });
        
        /**
         * Settings Page
         */
        
        // Form submission confirmation
        $('#wp-easy-staging-settings-form').on('submit', function() {
            return confirm(wp_easy_staging_i18n.confirm_settings);
        });
    });
    
    /**
     * Show loading overlay
     */
    function showLoadingOverlay(message) {
        // Create overlay if it doesn't exist
        if ($('.wp-easy-staging-loading').length === 0) {
            $('<div class="wp-easy-staging-loading"><div class="wp-easy-staging-loading-inner"><span class="spinner is-active"></span><p class="loading-message"></p></div></div>').appendTo('body');
        }
        
        // Set message
        $('.wp-easy-staging-loading .loading-message').text(message || wp_easy_staging_i18n.processing);
        
        // Show overlay
        $('.wp-easy-staging-loading').show();
    }
    
    /**
     * Hide loading overlay
     */
    function hideLoadingOverlay() {
        $('.wp-easy-staging-loading').hide();
    }

})( jQuery ); 