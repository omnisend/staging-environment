<?php
/**
 * Provides the admin area view for creating a staging site
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

// Load options
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
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wp-easy-staging-container">
        <div class="wp-easy-staging-card">
            <h2><?php _e('Create Staging Site', 'wp-easy-staging'); ?></h2>
            
            <form method="post" id="wp-easy-staging-create-form">
                <div class="form-field">
                    <label for="staging_name"><?php _e('Staging Name', 'wp-easy-staging'); ?></label>
                    <input type="text" id="staging_name" name="staging_name" placeholder="<?php _e('e.g., Staging, Dev, Test', 'wp-easy-staging'); ?>" value="staging" />
                    <p class="description"><?php _e('This will be used as a prefix for your staging URL.', 'wp-easy-staging'); ?></p>
                </div>
                
                <div class="form-field">
                    <h3><?php _e('Advanced Options', 'wp-easy-staging'); ?></h3>
                    <div class="advanced-toggle">
                        <a href="#" class="toggle-advanced"><?php _e('Show Advanced Options', 'wp-easy-staging'); ?></a>
                    </div>
                </div>
                
                <div class="advanced-options" style="display: none;">
                    <div class="form-field">
                        <h4><?php _e('Exclude Directories', 'wp-easy-staging'); ?></h4>
                        <p class="description"><?php _e('These directories will not be copied to the staging site. One per line.', 'wp-easy-staging'); ?></p>
                        <textarea name="exclude_dirs" rows="5"><?php echo esc_textarea(implode("\n", $exclude_dirs)); ?></textarea>
                    </div>
                    
                    <div class="form-field">
                        <h4><?php _e('Exclude File Extensions', 'wp-easy-staging'); ?></h4>
                        <p class="description"><?php _e('Files with these extensions will not be copied to the staging site. One per line.', 'wp-easy-staging'); ?></p>
                        <textarea name="exclude_extensions" rows="5"><?php echo esc_textarea(implode("\n", $exclude_extensions)); ?></textarea>
                    </div>
                    
                    <div class="form-field">
                        <h4><?php _e('Exclude Database Tables', 'wp-easy-staging'); ?></h4>
                        <p class="description"><?php _e('These tables will not be copied to the staging site. One per line. You can use wildcards, e.g., "wp_wc_*".', 'wp-easy-staging'); ?></p>
                        <textarea name="exclude_tables" rows="5"><?php echo esc_textarea(implode("\n", $exclude_tables)); ?></textarea>
                    </div>
                </div>
                
                <div class="form-field">
                    <div class="submit-container">
                        <input type="hidden" name="action" value="wp_easy_staging_create_staging" />
                        <?php wp_nonce_field('wp_easy_staging_nonce', 'wp_easy_staging_nonce'); ?>
                        <button type="submit" class="button button-primary"><?php _e('Create Staging', 'wp-easy-staging'); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging')); ?>" class="button"><?php _e('Cancel', 'wp-easy-staging'); ?></a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="wp-easy-staging-card wp-easy-staging-info-card">
            <h2><?php _e('About Staging Sites', 'wp-easy-staging'); ?></h2>
            
            <div class="wp-easy-staging-info-content">
                <p><?php _e('A staging site is a copy of your live website that you can use to test changes without affecting your production site.', 'wp-easy-staging'); ?></p>
                
                <h3><?php _e('What happens when you create a staging site?', 'wp-easy-staging'); ?></h3>
                <ol>
                    <li><?php _e('The plugin creates a copy of your website files in a new directory.', 'wp-easy-staging'); ?></li>
                    <li><?php _e('It copies your database tables and prefixes them with "staging_".', 'wp-easy-staging'); ?></li>
                    <li><?php _e('It sets up the staging site with its own WordPress configuration.', 'wp-easy-staging'); ?></li>
                </ol>
                
                <h3><?php _e('Important Notes', 'wp-easy-staging'); ?></h3>
                <ul>
                    <li><?php _e('Creating a staging site may take several minutes depending on the size of your website.', 'wp-easy-staging'); ?></li>
                    <li><?php _e('Ensure you have enough disk space available. You\'ll need approximately twice the size of your current website.', 'wp-easy-staging'); ?></li>
                    <li><?php _e('Exclude large files or directories you don\'t need in staging to speed up the process.', 'wp-easy-staging'); ?></li>
                    <li><?php _e('For very large sites, consider creating the staging site during low-traffic periods.', 'wp-easy-staging'); ?></li>
                </ul>
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
    
    .form-field {
        margin-bottom: 20px;
    }
    
    .form-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .form-field input[type="text"] {
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
    
    .advanced-toggle {
        margin-bottom: 10px;
    }
    
    .submit-container {
        margin-top: 30px;
    }
    
    .wp-easy-staging-info-content {
        font-size: 14px;
    }
    
    .wp-easy-staging-info-content h3 {
        margin-top: 20px;
        margin-bottom: 10px;
    }
    
    .wp-easy-staging-info-content ul,
    .wp-easy-staging-info-content ol {
        margin-left: 20px;
    }
    
    .wp-easy-staging-info-content li {
        margin-bottom: 8px;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Define ajaxurl if it's not already defined
        if (typeof ajaxurl === 'undefined') {
            var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        }
        
        // Toggle advanced options
        $('.toggle-advanced').on('click', function(e) {
            e.preventDefault();
            
            const advancedOptions = $('.advanced-options');
            
            if (advancedOptions.is(':visible')) {
                advancedOptions.slideUp();
                $(this).text('<?php _e('Show Advanced Options', 'wp-easy-staging'); ?>');
            } else {
                advancedOptions.slideDown();
                $(this).text('<?php _e('Hide Advanced Options', 'wp-easy-staging'); ?>');
            }
        });
        
        // Form submission
        $('#wp-easy-staging-create-form').on('submit', function(e) {
            e.preventDefault();
            
            // Show loading overlay
            $('<div class="wp-easy-staging-loading"><div class="wp-easy-staging-loading-inner"><span class="spinner is-active"></span><p><?php _e('Creating staging site. This may take several minutes...', 'wp-easy-staging'); ?></p></div></div>')
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
            
            $('.spinner').css({
                float: 'none',
                width: '20px',
                height: '20px',
                marginRight: '10px',
                verticalAlign: 'middle',
                visibility: 'visible'
            });
            
            // Submit form via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('.wp-easy-staging-loading').remove();
                    
                    if (response.success) {
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging&created=1')); ?>';
                    } else {
                        alert(response.data.message || '<?php _e('An error occurred. Please try again.', 'wp-easy-staging'); ?>');
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