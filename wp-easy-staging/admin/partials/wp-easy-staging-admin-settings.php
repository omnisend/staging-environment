<?php
/**
 * Provides the admin area view for plugin settings
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

// Get current settings
$options = get_option('wp_easy_staging_settings', array());

// Default exclusions
$exclude_dirs = isset($options['exclude_dirs']) ? $options['exclude_dirs'] : array(
    'wp-content/uploads/woocommerce_uploads',
    'wp-content/uploads/backups',
    'wp-content/cache',
    'wp-content/updraft',
);

$exclude_extensions = isset($options['exclude_extensions']) ? $options['exclude_extensions'] : array(
    'zip',
    'gz',
    'bz2',
    'sql',
    'log',
);

$exclude_tables = isset($options['exclude_tables']) ? $options['exclude_tables'] : array(
    'transient',
    'statistics',
    'session',
);

// Other settings
$staging_directory = isset($options['staging_directory']) ? $options['staging_directory'] : 'staging';
$create_subdomain = isset($options['create_subdomain']) ? $options['create_subdomain'] : 'yes';
$automatic_backups = isset($options['automatic_backups']) ? $options['automatic_backups'] : 'yes';
$backup_before_push = isset($options['backup_before_push']) ? $options['backup_before_push'] : 'yes';
$email_notifications = isset($options['email_notifications']) ? $options['email_notifications'] : 'yes';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'wp-easy-staging'); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="wp-easy-staging-container">
        <div class="wp-easy-staging-card">
            <form method="post" action="options.php" id="wp-easy-staging-settings-form">
                <?php settings_fields('wp_easy_staging_settings'); ?>
                
                <div class="wp-easy-staging-settings-section">
                    <h2><?php _e('General Settings', 'wp-easy-staging'); ?></h2>
                    
                    <div class="form-field">
                        <label for="staging_directory"><?php _e('Staging Directory', 'wp-easy-staging'); ?></label>
                        <input type="text" id="staging_directory" name="wp_easy_staging_settings[staging_directory]" value="<?php echo esc_attr($staging_directory); ?>" />
                        <p class="description"><?php _e('The directory name where staging sites will be created.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="create_subdomain"><?php _e('Create Subdomain', 'wp-easy-staging'); ?></label>
                        <select id="create_subdomain" name="wp_easy_staging_settings[create_subdomain]">
                            <option value="yes" <?php selected($create_subdomain, 'yes'); ?>><?php _e('Yes (e.g., staging.example.com)', 'wp-easy-staging'); ?></option>
                            <option value="no" <?php selected($create_subdomain, 'no'); ?>><?php _e('No (e.g., example.com/staging)', 'wp-easy-staging'); ?></option>
                        </select>
                        <p class="description"><?php _e('Create a subdomain for staging sites instead of a subdirectory. Requires DNS configuration.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="automatic_backups"><?php _e('Automatic Backups', 'wp-easy-staging'); ?></label>
                        <select id="automatic_backups" name="wp_easy_staging_settings[automatic_backups]">
                            <option value="yes" <?php selected($automatic_backups, 'yes'); ?>><?php _e('Yes', 'wp-easy-staging'); ?></option>
                            <option value="no" <?php selected($automatic_backups, 'no'); ?>><?php _e('No', 'wp-easy-staging'); ?></option>
                        </select>
                        <p class="description"><?php _e('Automatically create a backup before creating a staging site.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="backup_before_push"><?php _e('Backup Before Push', 'wp-easy-staging'); ?></label>
                        <select id="backup_before_push" name="wp_easy_staging_settings[backup_before_push]">
                            <option value="yes" <?php selected($backup_before_push, 'yes'); ?>><?php _e('Yes', 'wp-easy-staging'); ?></option>
                            <option value="no" <?php selected($backup_before_push, 'no'); ?>><?php _e('No', 'wp-easy-staging'); ?></option>
                        </select>
                        <p class="description"><?php _e('Automatically create a backup before pushing changes to production.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="email_notifications"><?php _e('Email Notifications', 'wp-easy-staging'); ?></label>
                        <select id="email_notifications" name="wp_easy_staging_settings[email_notifications]">
                            <option value="yes" <?php selected($email_notifications, 'yes'); ?>><?php _e('Yes', 'wp-easy-staging'); ?></option>
                            <option value="no" <?php selected($email_notifications, 'no'); ?>><?php _e('No', 'wp-easy-staging'); ?></option>
                        </select>
                        <p class="description"><?php _e('Send email notifications for important events (staging creation, push completion, etc.).', 'wp-easy-staging'); ?></p>
                    </div>
                </div>
                
                <div class="wp-easy-staging-settings-section">
                    <h2><?php _e('Exclusion Settings', 'wp-easy-staging'); ?></h2>
                    <p><?php _e('These items will be excluded when creating a staging site. This can help reduce the size of your staging site and speed up the cloning process.', 'wp-easy-staging'); ?></p>
                    
                    <div class="form-field">
                        <label for="exclude_dirs"><?php _e('Exclude Directories', 'wp-easy-staging'); ?></label>
                        <textarea id="exclude_dirs" name="wp_easy_staging_settings[exclude_dirs]" rows="5"><?php echo esc_textarea(implode("\n", $exclude_dirs)); ?></textarea>
                        <p class="description"><?php _e('Directories to exclude when creating a staging site. One per line.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="exclude_extensions"><?php _e('Exclude File Extensions', 'wp-easy-staging'); ?></label>
                        <textarea id="exclude_extensions" name="wp_easy_staging_settings[exclude_extensions]" rows="5"><?php echo esc_textarea(implode("\n", $exclude_extensions)); ?></textarea>
                        <p class="description"><?php _e('File extensions to exclude when creating a staging site. One per line.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="exclude_tables"><?php _e('Exclude Database Tables', 'wp-easy-staging'); ?></label>
                        <textarea id="exclude_tables" name="wp_easy_staging_settings[exclude_tables]" rows="5"><?php echo esc_textarea(implode("\n", $exclude_tables)); ?></textarea>
                        <p class="description"><?php _e('Database tables to exclude when creating a staging site. One per line. You can use wildcards, e.g., "wp_wc_*".', 'wp-easy-staging'); ?></p>
                    </div>
                </div>
                
                <div class="wp-easy-staging-settings-section">
                    <h2><?php _e('Advanced Settings', 'wp-easy-staging'); ?></h2>
                    
                    <div class="form-field">
                        <label for="file_copy_method"><?php _e('File Copy Method', 'wp-easy-staging'); ?></label>
                        <select id="file_copy_method" name="wp_easy_staging_settings[file_copy_method]">
                            <option value="direct" <?php selected(isset($options['file_copy_method']) ? $options['file_copy_method'] : 'direct', 'direct'); ?>><?php _e('Direct Copy (Faster)', 'wp-easy-staging'); ?></option>
                            <option value="chunked" <?php selected(isset($options['file_copy_method']) ? $options['file_copy_method'] : 'direct', 'chunked'); ?>><?php _e('Chunked Copy (Better for large sites)', 'wp-easy-staging'); ?></option>
                        </select>
                        <p class="description"><?php _e('Method used to copy files when creating a staging site.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="db_query_limit"><?php _e('Database Query Limit', 'wp-easy-staging'); ?></label>
                        <input type="number" id="db_query_limit" name="wp_easy_staging_settings[db_query_limit]" value="<?php echo esc_attr(isset($options['db_query_limit']) ? $options['db_query_limit'] : 500); ?>" min="100" max="10000" step="100" />
                        <p class="description"><?php _e('Maximum number of rows to process in a single database query.', 'wp-easy-staging'); ?></p>
                    </div>
                    
                    <div class="form-field">
                        <label for="timeout"><?php _e('Operation Timeout', 'wp-easy-staging'); ?></label>
                        <input type="number" id="timeout" name="wp_easy_staging_settings[timeout]" value="<?php echo esc_attr(isset($options['timeout']) ? $options['timeout'] : 300); ?>" min="60" max="3600" step="30" />
                        <p class="description"><?php _e('Maximum time in seconds for operations like staging creation or pushing changes.', 'wp-easy-staging'); ?></p>
                    </div>
                </div>
                
                <div class="wp-easy-staging-settings-submit">
                    <?php submit_button(__('Save Settings', 'wp-easy-staging'), 'primary', 'submit', false); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging')); ?>" class="button"><?php _e('Cancel', 'wp-easy-staging'); ?></a>
                </div>
            </form>
        </div>
        
        <div class="wp-easy-staging-card wp-easy-staging-info-card">
            <h2><?php _e('Settings Information', 'wp-easy-staging'); ?></h2>
            
            <div class="wp-easy-staging-info-content">
                <p><?php _e('These settings control the behavior of the WP Easy Staging plugin.', 'wp-easy-staging'); ?></p>
                
                <h3><?php _e('General Settings', 'wp-easy-staging'); ?></h3>
                <p><?php _e('Configure the basic functionality of the plugin, such as where staging sites are created and whether to use subdomains.', 'wp-easy-staging'); ?></p>
                
                <h3><?php _e('Exclusion Settings', 'wp-easy-staging'); ?></h3>
                <p><?php _e('Exclude specific directories, file types, or database tables from being copied to the staging site. This can significantly reduce the size of your staging site and speed up the cloning process.', 'wp-easy-staging'); ?></p>
                
                <h3><?php _e('Advanced Settings', 'wp-easy-staging'); ?></h3>
                <p><?php _e('Fine-tune the performance of the plugin. These settings are typically only needed for very large websites or in specific environments.', 'wp-easy-staging'); ?></p>
                
                <div class="wp-easy-staging-info-note">
                    <p><strong><?php _e('Note:', 'wp-easy-staging'); ?></strong> <?php _e('If you\'re experiencing timeouts or memory issues when creating staging sites, try these adjustments:', 'wp-easy-staging'); ?></p>
                    <ul>
                        <li><?php _e('Exclude more directories or file types', 'wp-easy-staging'); ?></li>
                        <li><?php _e('Change the file copy method to "Chunked Copy"', 'wp-easy-staging'); ?></li>
                        <li><?php _e('Reduce the database query limit', 'wp-easy-staging'); ?></li>
                        <li><?php _e('Increase the PHP memory limit and max execution time in your server configuration', 'wp-easy-staging'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .wp-easy-staging-container {
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
    
    .wp-easy-staging-settings-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .wp-easy-staging-settings-section:last-of-type {
        border-bottom: none;
    }
    
    .form-field {
        margin-bottom: 20px;
    }
    
    .form-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .form-field input[type="text"],
    .form-field input[type="number"],
    .form-field select {
        width: 100%;
        max-width: 400px;
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #8c8f94;
    }
    
    .form-field textarea {
        width: 100%;
        max-width: 400px;
    }
    
    .description {
        color: #646970;
        font-size: 13px;
        margin-top: 4px;
        margin-bottom: 0;
    }
    
    .wp-easy-staging-settings-submit {
        margin-top: 30px;
    }
    
    .wp-easy-staging-info-content {
        font-size: 14px;
    }
    
    .wp-easy-staging-info-content h3 {
        margin-top: 20px;
        margin-bottom: 10px;
    }
    
    .wp-easy-staging-info-note {
        background-color: #f0f6fc;
        border-left: 4px solid #2271b1;
        padding: 12px 15px;
        margin-top: 20px;
    }
    
    .wp-easy-staging-info-note ul {
        margin-left: 20px;
        margin-bottom: 0;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Form submission confirmation
        $('#wp-easy-staging-settings-form').on('submit', function() {
            return confirm('<?php _e('Are you sure you want to save these settings? Changing some settings may affect existing staging sites.', 'wp-easy-staging'); ?>');
        });
    });
</script> 