/**
 * Staging2Live Admin JavaScript
 */
(function($) {
    'use strict';

    // Variables
    var $selectedFiles = [];
    var $selectedDBEntries = [];
    var $fileDiffModal = null;
    var $dbDiffModal = null;
    var $syncingChanges = false;

    // Initialize
    $(function() {
        // Initialize tabs
        initTabs();

        // Initialize modals
        initModals();

        // Initialize file diff view
        initFileDiffView();

        // Initialize DB diff view
        initDBDiffView();

        // Initialize checkboxes
        initCheckboxes();

        // Initialize sync button
        initSyncButton();
    });

    /**
     * Initialize tabs
     */
    function initTabs() {
        var $tabs = $('.stl-tabs');

        if ($tabs.length === 0) {
            return;
        }

        var $nav = $tabs.find('.stl-tabs-nav a');
        var $content = $tabs.find('.stl-tab-content');

        // Set first tab as active by default
        $nav.first().addClass('active');
        $content.first().addClass('active');

        // Handle tab click
        $nav.on('click', function(e) {
            e.preventDefault();

            var target = $(this).attr('href');

            // Remove active class from all tabs
            $nav.removeClass('active');
            $content.removeClass('active');

            // Add active class to clicked tab
            $(this).addClass('active');
            $(target).addClass('active');
        });
    }

    /**
     * Initialize modals
     */
    function initModals() {
        // Create file diff modal
        $('body').append(
            '<div id="stl-file-diff-modal" class="stl-modal">' +
                '<div class="stl-modal-content">' +
                    '<span class="stl-modal-close">&times;</span>' +
                    '<h2 class="stl-modal-title"></h2>' +
                    '<div class="stl-modal-body"></div>' +
                '</div>' +
            '</div>'
        );

        // Create DB diff modal
        $('body').append(
            '<div id="stl-db-diff-modal" class="stl-modal">' +
                '<div class="stl-modal-content">' +
                    '<span class="stl-modal-close">&times;</span>' +
                    '<h2 class="stl-modal-title"></h2>' +
                    '<div class="stl-modal-body"></div>' +
                '</div>' +
            '</div>'
        );

        // Set modal references
        $fileDiffModal = $('#stl-file-diff-modal');
        $dbDiffModal = $('#stl-db-diff-modal');

        // Handle close button click
        $('.stl-modal-close').on('click', function() {
            $(this).closest('.stl-modal').hide();
        });

        // Handle click outside modal
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('stl-modal')) {
                $('.stl-modal').hide();
            }
        });
    }

    /**
     * Initialize file diff view
     */
    function initFileDiffView() {
        $(document).on('click', '.stl-view-diff', function(e) {
            e.preventDefault();

            var file = $(this).data('file');

            // Set modal title
            $fileDiffModal.find('.stl-modal-title').text('File Diff: ' + file);

            // Set loading indicator
            $fileDiffModal.find('.stl-modal-body').html('<div class="stl-loading"></div>');

            // Show modal
            $fileDiffModal.show();

            // Get file diff via AJAX
            $.ajax({
                url: stl_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'stl_get_file_diff',
                    nonce: stl_admin.nonce,
                    file: file
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.is_binary) {
                            // Show binary file info
                            var html = '<div class="stl-binary-info">';
                            html += '<p>This is a binary file. Diff cannot be displayed.</p>';

                            if (response.data.staging_exists && response.data.production_exists) {
                                html += '<p>File exists in both staging and production environments.</p>';
                                html += '<p>Staging file size: ' + formatBytes(response.data.staging_size) + '</p>';
                                html += '<p>Production file size: ' + formatBytes(response.data.production_size) + '</p>';
                            } else if (response.data.staging_exists) {
                                html += '<p>File exists only in staging environment.</p>';
                                html += '<p>File size: ' + formatBytes(response.data.staging_size) + '</p>';
                            } else {
                                html += '<p>File exists only in production environment.</p>';
                                html += '<p>File size: ' + formatBytes(response.data.production_size) + '</p>';
                            }

                            html += '</div>';

                            $fileDiffModal.find('.stl-modal-body').html(html);
                        } else {
                            // Show diff
                            $fileDiffModal.find('.stl-modal-body').html('<div class="stl-diff">' + response.data.diff + '</div>');
                        }
                    } else {
                        $fileDiffModal.find('.stl-modal-body').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $fileDiffModal.find('.stl-modal-body').html('<div class="notice notice-error"><p>Error fetching file diff.</p></div>');
                }
            });
        });
    }

    /**
     * Initialize DB diff view
     */
    function initDBDiffView() {
        $(document).on('click', '.stl-view-db-diff', function(e) {
            e.preventDefault();

            var table = $(this).data('table');
            var id = $(this).data('id');

            // Set modal title
            $dbDiffModal.find('.stl-modal-title').text('Database Diff: ' + table + ' (ID: ' + id + ')');

            // Set loading indicator
            $dbDiffModal.find('.stl-modal-body').html('<div class="stl-loading"></div>');

            // Show modal
            $dbDiffModal.show();

            // Get DB diff via AJAX
            $.ajax({
                url: stl_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'stl_get_db_diff',
                    nonce: stl_admin.nonce,
                    table: table,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        var html = '';
                        var entryName = response.data.entry_name ? response.data.entry_name : 'ID: ' + response.data.id;

                        if (response.data.type === 'added') {
                            // New entry
                            html += '<div class="notice notice-success"><p>This is a new entry in the staging environment.</p></div>';
                            html += '<p><strong>Entry: ' + entryName + '</strong></p>';

                            html += '<table class="stl-db-diff-table">';
                            html += '<tr><th>Field</th><th>Value</th></tr>';

                            for (var field in response.data.diff.staging_data) {
                                html += '<tr>';
                                html += '<td class="field-name">' + field + '</td>';
                                html += '<td>' + formatValue(response.data.diff.staging_data[field]) + '</td>';
                                html += '</tr>';
                            }

                            html += '</table>';
                        } else if (response.data.type === 'deleted') {
                            // Deleted entry
                            html += '<div class="notice notice-warning"><p>This entry has been deleted in the staging environment.</p></div>';
                            html += '<p><strong>Entry: ' + entryName + '</strong></p>';

                            html += '<table class="stl-db-diff-table">';
                            html += '<tr><th>Field</th><th>Value</th></tr>';

                            for (var field in response.data.diff.production_data) {
                                html += '<tr>';
                                html += '<td class="field-name">' + field + '</td>';
                                html += '<td>' + formatValue(response.data.diff.production_data[field]) + '</td>';
                                html += '</tr>';
                            }

                            html += '</table>';
                        } else {
                            // Modified entry
                            html += '<div class="notice notice-info"><p>This entry has been modified in the staging environment.</p></div>';
                            html += '<p><strong>Entry: ' + entryName + '</strong></p>';

                            html += '<table class="stl-db-diff-table">';
                            html += '<tr><th>Field</th><th>Production Value</th><th>Staging Value</th></tr>';

                            for (var field in response.data.diff.field_changes) {
                                var change = response.data.diff.field_changes[field];

                                html += '<tr>';
                                html += '<td class="field-name">' + field + '</td>';
                                html += '<td class="production-value">' + formatValue(change.production) + '</td>';
                                html += '<td class="staging-value">' + formatValue(change.staging) + '</td>';
                                html += '</tr>';
                            }

                            html += '</table>';
                        }

                        $dbDiffModal.find('.stl-modal-body').html(html);
                    } else {
                        $dbDiffModal.find('.stl-modal-body').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $dbDiffModal.find('.stl-modal-body').html('<div class="notice notice-error"><p>Error fetching database diff.</p></div>');
                }
            });
        });
    }

    /**
     * Initialize checkboxes
     */
    function initCheckboxes() {
        // Select all files checkbox
        $('#stl-select-all-files').on('change', function() {
            var isChecked = $(this).prop('checked');

            $('.stl-select-file').prop('checked', isChecked);

            // Update selected files array
            $selectedFiles = [];

            if (isChecked) {
                $('.stl-select-file').each(function() {
                    $selectedFiles.push($(this).val());
                });
            }
        });

        // Individual file checkboxes
        $(document).on('change', '.stl-select-file', function() {
            var file = $(this).val();

            if ($(this).prop('checked')) {
                // Add file to selected array
                if ($selectedFiles.indexOf(file) === -1) {
                    $selectedFiles.push(file);
                }
            } else {
                // Remove file from selected array
                var index = $selectedFiles.indexOf(file);
                if (index !== -1) {
                    $selectedFiles.splice(index, 1);
                }

                // Uncheck select all checkbox
                $('#stl-select-all-files').prop('checked', false);
            }
        });

        // Select all DB entries checkbox
        $('#stl-select-all-db').on('change', function() {
            var isChecked = $(this).prop('checked');

            $('.stl-select-db').prop('checked', isChecked);

            // Update selected DB entries array
            $selectedDBEntries = [];

            if (isChecked) {
                $('.stl-select-db').each(function() {
                    $selectedDBEntries.push(JSON.parse($(this).val()));
                });
            }
        });

        // Individual DB entry checkboxes
        $(document).on('change', '.stl-select-db', function() {
            var entry = JSON.parse($(this).val());

            if ($(this).prop('checked')) {
                // Add entry to selected array
                var exists = false;

                for (var i = 0; i < $selectedDBEntries.length; i++) {
                    if ($selectedDBEntries[i].table === entry.table && $selectedDBEntries[i].id === entry.id) {
                        exists = true;
                        break;
                    }
                }

                if (!exists) {
                    $selectedDBEntries.push(entry);
                }
            } else {
                // Remove entry from selected array
                for (var i = 0; i < $selectedDBEntries.length; i++) {
                    if ($selectedDBEntries[i].table === entry.table && $selectedDBEntries[i].id === entry.id) {
                        $selectedDBEntries.splice(i, 1);
                        break;
                    }
                }

                // Uncheck select all checkbox
                $('#stl-select-all-db').prop('checked', false);
            }
        });
    }

    /**
     * Initialize sync button
     */
    function initSyncButton() {
        $('#stl-sync-selected').on('click', function() {
            if ($syncingChanges) {
                return;
            }

            // Check if anything is selected
            if ($selectedFiles.length === 0 && $selectedDBEntries.length === 0) {
                alert('Please select at least one change to sync.');
                return;
            }

            // Confirm action
            if (!confirm('Are you sure you want to sync the selected changes from staging to production? This action cannot be undone.')) {
                return;
            }

            // Set syncing flag
            $syncingChanges = true;

            // Add loading indicator
            $(this).append('<span class="stl-loading"></span>');
            $(this).prop('disabled', true);

            // Sync changes via AJAX
            $.ajax({
                url: stl_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'stl_sync_changes',
                    nonce: stl_admin.nonce,
                    files: JSON.stringify($selectedFiles),
                    tables: JSON.stringify($selectedDBEntries)
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success is-dismissible"><p>Changes synced successfully!</p></div>';

                        // Show file sync results
                        if (response.data.files.success && Object.keys(response.data.files.success).length > 0) {
                            html += '<h3>File Sync Results</h3>';
                            html += '<ul class="stl-success-list">';

                            for (var file in response.data.files.success) {
                                html += '<li><strong>' + file + ':</strong> ' + response.data.files.success[file] + '</li>';
                            }

                            html += '</ul>';
                        }

                        // Show file sync errors
                        if (response.data.files.error && Object.keys(response.data.files.error).length > 0) {
                            html += '<h3>File Sync Errors</h3>';
                            html += '<ul class="stl-error-list">';

                            for (var file in response.data.files.error) {
                                html += '<li><strong>' + file + ':</strong> ' + response.data.files.error[file] + '</li>';
                            }

                            html += '</ul>';
                        }

                        // Show DB sync results
                        if (response.data.db.success && response.data.db.success.length > 0) {
                            html += '<h3>Database Sync Results</h3>';
                            html += '<ul class="stl-success-list">';

                            for (var i = 0; i < response.data.db.success.length; i++) {
                                html += '<li>' + response.data.db.success[i] + '</li>';
                            }

                            html += '</ul>';
                        }

                        // Show DB sync errors
                        if (response.data.db.error && response.data.db.error.length > 0) {
                            html += '<h3>Database Sync Errors</h3>';
                            html += '<ul class="stl-error-list">';

                            for (var i = 0; i < response.data.db.error.length; i++) {
                                html += '<li>' + response.data.db.error[i] + '</li>';
                            }

                            html += '</ul>';
                        }

                        // Add refresh button
                        html += '<p><a href="' + window.location.href + '" class="button button-primary">Refresh Page</a></p>';

                        // Create result modal
                        $('body').append(
                            '<div id="stl-sync-result-modal" class="stl-modal">' +
                                '<div class="stl-modal-content">' +
                                    '<span class="stl-modal-close">&times;</span>' +
                                    '<h2 class="stl-modal-title">Sync Results</h2>' +
                                    '<div class="stl-modal-body">' + html + '</div>' +
                                '</div>' +
                            '</div>'
                        );

                        // Show result modal
                        $('#stl-sync-result-modal').show();

                        // Handle close button click
                        $('#stl-sync-result-modal .stl-modal-close').on('click', function() {
                            window.location.reload();
                        });
                    } else {
                        alert('Error syncing changes: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error syncing changes. Please try again.');
                },
                complete: function() {
                    // Reset syncing flag
                    $syncingChanges = false;

                    // Remove loading indicator
                    $('#stl-sync-selected .stl-loading').remove();
                    $('#stl-sync-selected').prop('disabled', false);
                }
            });
        });
    }

    /**
     * Format bytes to human-readable format
     */
    function formatBytes(bytes, decimals) {
        if (bytes === 0) return '0 Bytes';

        var k = 1024;
        var dm = decimals || 2;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    /**
     * Format value for display
     */
    function formatValue(value) {
        if (value === null) {
            return '<em>NULL</em>';
        }

        if (value === '') {
            return '<em>empty string</em>';
        }

        return String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

})(jQuery);

jQuery(document).ready(function(){

    jQuery("a#create-staging").click( function( event ) {

        jQuery('#response').html('');

        jQuery( "a#create-staging" ).css( { "pointer-events":"none", "color":"lightgrey" } );

        jQuery( "span.spinner-create-staging" ).addClass("is-active")
            .css( { "float":"left", "visibility":"" } );

        const data = {
            'action': 'create_staging',
            nonce: stl.nonce
        };

        // Ajax-Anfrage an den WordPress-Server senden
        jQuery.post(ajaxurl, data, function(response) {
            if (response.error) {
                jQuery('#response').html('<p style="color: red;">' + response.data.message + '</p>');
            } else {
                jQuery('#response').html('<p style="color: green;">' + response.data.message + '</p>');


                jQuery( "span.spinner-create-staging" ).removeClass("is-active")
                    .css( { "float":"right", "visibility":"none" } );

                jQuery( "a#create-staging" ).css( { "pointer-events":"unset", "color":"#fff" } );
            }
        });
    });

});
