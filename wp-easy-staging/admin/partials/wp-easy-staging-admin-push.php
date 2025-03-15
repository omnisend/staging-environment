<?php
/**
 * Provides the admin area view for pushing changes from staging to production
 *
 * @link       https://github.com/omnisend/wp-easy-staging
 * @since      1.0.0
 *
 * @package    WP_Easy_Staging
 * @subpackage WP_Easy_Staging/admin/partials
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Get staging info
$staging_instance = new WP_Easy_Staging_Staging();
$staging_info = $staging_instance->get_staging_status();

// If no staging exists, redirect to main page
if (!$staging_info['has_staging']) {
    wp_redirect(admin_url('admin.php?page=wp-easy-staging'));
    exit;
}

// Get pushing instance
$pushing = new WP_Easy_Staging_Pushing();

// Get changes
$changes = $pushing->get_available_changes();
$database_changes = $changes['database'];
$file_changes = $changes['files'];

// Get conflict detector
$conflict_detector = new WP_Easy_Staging_Conflict_Detector();

// Check for conflicts
$has_conflicts = false;
$unresolved_conflicts = $conflict_detector->get_unresolved_conflicts($staging_info['staging']['id']);
if (!empty($unresolved_conflicts)) {
    $has_conflicts = true;
}

// Check if we're in conflict resolution mode
$conflict_mode = isset($_GET['resolve_conflicts']) && $_GET['resolve_conflicts'] == '1';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if ($conflict_mode && $has_conflicts) : ?>
        <!-- Conflict Resolution Mode -->
        <div class="wp-easy-staging-section">
            <div class="wp-easy-staging-card">
                <h2><?php _e('Resolve Conflicts', 'wp-easy-staging'); ?></h2>
                <p><?php _e('The following conflicts were detected between your staging and production sites. Please resolve each conflict before pushing changes.', 'wp-easy-staging'); ?></p>
                
                <form id="wp-easy-staging-resolve-conflicts" method="post">
                    <?php foreach ($unresolved_conflicts as $conflict) : ?>
                        <?php 
                        $conflict_details = $conflict_detector->get_conflict_details($conflict->id);
                        $conflict_resolver = new WP_Easy_Staging_Conflict_Resolver();
                        $resolution_options = $conflict_resolver->get_resolution_options($conflict->id);
                        
                        $conflict_type = $conflict->type;
                        $conflict_item = $conflict->item;
                        ?>
                        
                        <div class="wp-easy-staging-conflict-item" data-id="<?php echo esc_attr($conflict->id); ?>">
                            <h3><?php echo sprintf(__('Conflict #%d: %s', 'wp-easy-staging'), $conflict->id, $conflict_item); ?></h3>
                            
                            <div class="wp-easy-staging-conflict-details">
                                <div class="wp-easy-staging-conflict-type">
                                    <strong><?php _e('Type:', 'wp-easy-staging'); ?></strong> 
                                    <?php 
                                    if ($conflict_type === 'database') {
                                        echo esc_html__('Database', 'wp-easy-staging');
                                    } else {
                                        echo esc_html__('File', 'wp-easy-staging');
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($conflict_type === 'database') : ?>
                                    <div class="wp-easy-staging-conflict-table">
                                        <table class="widefat">
                                            <thead>
                                                <tr>
                                                    <th><?php _e('Field', 'wp-easy-staging'); ?></th>
                                                    <th><?php _e('Production Value', 'wp-easy-staging'); ?></th>
                                                    <th><?php _e('Staging Value', 'wp-easy-staging'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($conflict_details['fields'] as $field => $values) : ?>
                                                    <tr>
                                                        <td><strong><?php echo esc_html($field); ?></strong></td>
                                                        <td class="production-value"><?php echo esc_html($values['production']); ?></td>
                                                        <td class="staging-value"><?php echo esc_html($values['staging']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <div class="wp-easy-staging-conflict-file">
                                        <div class="wp-easy-staging-diff">
                                            <div class="diff-header">
                                                <div class="diff-header-production"><?php _e('Production Version', 'wp-easy-staging'); ?></div>
                                                <div class="diff-header-staging"><?php _e('Staging Version', 'wp-easy-staging'); ?></div>
                                            </div>
                                            <div class="diff-content">
                                                <?php echo wp_kses_post($conflict_details['diff']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="wp-easy-staging-conflict-resolution">
                                    <h4><?php _e('Resolution:', 'wp-easy-staging'); ?></h4>
                                    
                                    <div class="wp-easy-staging-resolution-options">
                                        <label>
                                            <input type="radio" name="resolution[<?php echo esc_attr($conflict->id); ?>]" value="staging" checked>
                                            <?php _e('Use Staging Version', 'wp-easy-staging'); ?>
                                        </label>
                                        
                                        <label>
                                            <input type="radio" name="resolution[<?php echo esc_attr($conflict->id); ?>]" value="production">
                                            <?php _e('Keep Production Version', 'wp-easy-staging'); ?>
                                        </label>
                                        
                                        <?php if (isset($resolution_options['can_merge']) && $resolution_options['can_merge']) : ?>
                                            <label>
                                                <input type="radio" name="resolution[<?php echo esc_attr($conflict->id); ?>]" value="custom">
                                                <?php _e('Custom Merge', 'wp-easy-staging'); ?>
                                            </label>
                                            
                                            <div class="custom-merge-container" style="display: none;">
                                                <textarea name="custom_merge[<?php echo esc_attr($conflict->id); ?>]" class="large-text code" rows="10"><?php echo esc_textarea($resolution_options['merged_content']); ?></textarea>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="wp-easy-staging-resolve-actions">
                        <input type="hidden" name="action" value="wp_easy_staging_resolve_conflicts" />
                        <?php wp_nonce_field('wp_easy_staging_nonce', 'wp_easy_staging_nonce'); ?>
                        <button type="submit" class="button button-primary"><?php _e('Resolve Conflicts & Continue', 'wp-easy-staging'); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging-push')); ?>" class="button"><?php _e('Cancel', 'wp-easy-staging'); ?></a>
                    </div>
                </form>
            </div>
        </div>
    <?php else : ?>
        <!-- Push Mode -->
        <div class="wp-easy-staging-section">
            <div class="wp-easy-staging-card">
                <h2><?php _e('Push Changes to Production', 'wp-easy-staging'); ?></h2>
                
                <?php if ($has_conflicts && !$conflict_mode) : ?>
                    <div class="wp-easy-staging-notice wp-easy-staging-notice-warning">
                        <p>
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('Conflicts detected! You need to resolve conflicts before pushing changes.', 'wp-easy-staging'); ?>
                        </p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging-push&resolve_conflicts=1')); ?>" class="button button-primary"><?php _e('Resolve Conflicts', 'wp-easy-staging'); ?></a>
                        </p>
                    </div>
                <?php elseif (empty($database_changes) && empty($file_changes)) : ?>
                    <div class="wp-easy-staging-notice wp-easy-staging-notice-info">
                        <p>
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('No changes detected between staging and production.', 'wp-easy-staging'); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <form id="wp-easy-staging-push-form" method="post">
                        <?php if (!empty($database_changes)) : ?>
                            <div class="wp-easy-staging-changes-section">
                                <h3><?php _e('Database Changes', 'wp-easy-staging'); ?></h3>
                                
                                <table class="widefat wp-easy-staging-changes-table">
                                    <thead>
                                        <tr>
                                            <th class="check-column">
                                                <input type="checkbox" id="select-all-db" checked />
                                            </th>
                                            <th><?php _e('Table', 'wp-easy-staging'); ?></th>
                                            <th><?php _e('Item', 'wp-easy-staging'); ?></th>
                                            <th><?php _e('Type', 'wp-easy-staging'); ?></th>
                                            <th><?php _e('Date Modified', 'wp-easy-staging'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($database_changes as $change) : ?>
                                            <tr>
                                                <td class="check-column">
                                                    <input type="checkbox" name="selected_items[]" value="<?php echo esc_attr('db:' . $change->id); ?>" checked />
                                                </td>
                                                <td><?php echo esc_html($change->table_name); ?></td>
                                                <td><?php echo esc_html($change->item_name); ?></td>
                                                <td>
                                                    <?php 
                                                    switch ($change->change_type) {
                                                        case 'insert':
                                                            echo '<span class="change-type change-insert">' . esc_html__('Added', 'wp-easy-staging') . '</span>';
                                                            break;
                                                        case 'update':
                                                            echo '<span class="change-type change-update">' . esc_html__('Modified', 'wp-easy-staging') . '</span>';
                                                            break;
                                                        case 'delete':
                                                            echo '<span class="change-type change-delete">' . esc_html__('Deleted', 'wp-easy-staging') . '</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($change->date_modified))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($file_changes)) : ?>
                            <div class="wp-easy-staging-changes-section">
                                <h3><?php _e('File Changes', 'wp-easy-staging'); ?></h3>
                                
                                <table class="widefat wp-easy-staging-changes-table">
                                    <thead>
                                        <tr>
                                            <th class="check-column">
                                                <input type="checkbox" id="select-all-files" checked />
                                            </th>
                                            <th><?php _e('File', 'wp-easy-staging'); ?></th>
                                            <th><?php _e('Type', 'wp-easy-staging'); ?></th>
                                            <th><?php _e('Date Modified', 'wp-easy-staging'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($file_changes as $change) : ?>
                                            <tr>
                                                <td class="check-column">
                                                    <input type="checkbox" name="selected_items[]" value="<?php echo esc_attr('file:' . $change->id); ?>" checked />
                                                </td>
                                                <td><?php echo esc_html($change->file_path); ?></td>
                                                <td>
                                                    <?php 
                                                    switch ($change->change_type) {
                                                        case 'added':
                                                            echo '<span class="change-type change-insert">' . esc_html__('Added', 'wp-easy-staging') . '</span>';
                                                            break;
                                                        case 'modified':
                                                            echo '<span class="change-type change-update">' . esc_html__('Modified', 'wp-easy-staging') . '</span>';
                                                            break;
                                                        case 'deleted':
                                                            echo '<span class="change-type change-delete">' . esc_html__('Deleted', 'wp-easy-staging') . '</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($change->date_modified))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <div class="wp-easy-staging-push-actions">
                            <input type="hidden" name="action" value="wp_easy_staging_push_changes" />
                            <?php wp_nonce_field('wp_easy_staging_nonce', 'wp_easy_staging_nonce'); ?>
                            <button type="submit" class="button button-primary"><?php _e('Push Selected Changes', 'wp-easy-staging'); ?></button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging')); ?>" class="button"><?php _e('Cancel', 'wp-easy-staging'); ?></a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="wp-easy-staging-card wp-easy-staging-info-card">
                <h2><?php _e('About Pushing Changes', 'wp-easy-staging'); ?></h2>
                
                <div class="wp-easy-staging-info-content">
                    <p><?php _e('Pushing changes will apply the selected modifications from your staging site to your production site.', 'wp-easy-staging'); ?></p>
                    
                    <h3><?php _e('Important Notes', 'wp-easy-staging'); ?></h3>
                    <ul>
                        <li><?php _e('Always backup your production site before pushing changes.', 'wp-easy-staging'); ?></li>
                        <li><?php _e('The plugin will automatically create a backup before applying changes.', 'wp-easy-staging'); ?></li>
                        <li><?php _e('You can select which changes to push and which to ignore.', 'wp-easy-staging'); ?></li>
                        <li><?php _e('If conflicts are detected, you\'ll need to resolve them before pushing.', 'wp-easy-staging'); ?></li>
                        <li><?php _e('For large changes, the push process may take several minutes.', 'wp-easy-staging'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .wp-easy-staging-section {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 20px;
    }
    
    .wp-easy-staging-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        padding: 20px;
        border-radius: 5px;
    }
    
    .wp-easy-staging-card:first-child {
        flex: 2;
        min-width: 500px;
    }
    
    .wp-easy-staging-info-card {
        flex: 1;
        min-width: 300px;
    }
    
    .wp-easy-staging-notice {
        padding: 10px 15px;
        border-left: 4px solid;
        margin-bottom: 20px;
    }
    
    .wp-easy-staging-notice-warning {
        border-color: #ffb900;
        background-color: #fff8e5;
    }
    
    .wp-easy-staging-notice-info {
        border-color: #00a0d2;
        background-color: #e5f5fa;
    }
    
    .wp-easy-staging-notice .dashicons {
        margin-right: 5px;
    }
    
    .wp-easy-staging-changes-section {
        margin-bottom: 30px;
    }
    
    .wp-easy-staging-changes-table {
        margin-top: 10px;
    }
    
    .wp-easy-staging-changes-table .check-column {
        width: 30px;
        text-align: center;
    }
    
    .change-type {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .change-insert {
        background-color: #dff0d8;
        color: #3c763d;
    }
    
    .change-update {
        background-color: #d9edf7;
        color: #31708f;
    }
    
    .change-delete {
        background-color: #f2dede;
        color: #a94442;
    }
    
    .wp-easy-staging-push-actions,
    .wp-easy-staging-resolve-actions {
        margin-top: 30px;
    }
    
    /* Conflict Resolution Styles */
    .wp-easy-staging-conflict-item {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #eee;
    }
    
    .wp-easy-staging-conflict-details {
        margin-top: 15px;
    }
    
    .wp-easy-staging-conflict-type {
        margin-bottom: 10px;
    }
    
    .wp-easy-staging-conflict-table table,
    .wp-easy-staging-conflict-file {
        margin-bottom: 20px;
    }
    
    .wp-easy-staging-conflict-table .production-value {
        background-color: #fff8e5;
    }
    
    .wp-easy-staging-conflict-table .staging-value {
        background-color: #e5f5fa;
    }
    
    .wp-easy-staging-resolution-options label {
        display: block;
        margin-bottom: 10px;
    }
    
    .wp-easy-staging-resolution-options .custom-merge-container {
        margin-top: 10px;
        margin-bottom: 20px;
    }
    
    /* Diff styles */
    .wp-easy-staging-diff {
        border: 1px solid #ddd;
        margin-bottom: 20px;
    }
    
    .diff-header {
        display: flex;
        background-color: #f5f5f5;
        border-bottom: 1px solid #ddd;
    }
    
    .diff-header-production,
    .diff-header-staging {
        flex: 1;
        padding: 8px 15px;
        font-weight: bold;
    }
    
    .diff-header-production {
        border-right: 1px solid #ddd;
    }
    
    .diff-content {
        display: flex;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .diff-content table {
        width: 100%;
    }
    
    .diff-content td {
        padding: 2px 5px;
        font-family: monospace;
        white-space: pre-wrap;
    }
    
    .diff-content td.deleted {
        background-color: #ffecec;
    }
    
    .diff-content td.added {
        background-color: #eaffea;
    }
    
    .diff-content td.unchanged {
        background-color: #f8f8f8;
    }
    
    .diff-line-number {
        width: 30px;
        color: #999;
        text-align: right;
        border-right: 1px solid #ddd;
        padding-right: 5px !important;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Select all checkboxes
        $('#select-all-db').on('change', function() {
            const isChecked = $(this).prop('checked');
            $(this).closest('table').find('tbody input[type="checkbox"][name="selected_items[]"][value^="db:"]').prop('checked', isChecked);
        });
        
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
        
        // Form submission
        $('#wp-easy-staging-push-form, #wp-easy-staging-resolve-conflicts').on('submit', function(e) {
            e.preventDefault();
            
            // Show loading overlay
            $('<div class="wp-easy-staging-loading"><div class="wp-easy-staging-loading-inner"><span class="spinner is-active"></span><p><?php _e('Processing changes. This may take several minutes...', 'wp-easy-staging'); ?></p></div></div>')
                .css({
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    backgroundColor: 'rgba(0, 0, 0, 0.5)',
                    zIndex: 99999,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center'
                })
                .appendTo('body');
            
            $('.wp-easy-staging-loading-inner').css({
                backgroundColor: '#fff',
                padding: '30px',
                borderRadius: '5px',
                textAlign: 'center'
            });
            
            // Submit form via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('.wp-easy-staging-loading').remove();
                    
                    if (response.success) {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.href = '<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging&pushed=1')); ?>';
                        }
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    $('.wp-easy-staging-loading').remove();
                    alert('<?php _e('An error occurred. Please try again.', 'wp-easy-staging'); ?>');
                }
            });
        });
    });
</script> 