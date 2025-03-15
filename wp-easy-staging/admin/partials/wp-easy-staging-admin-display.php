<?php
/**
 * Provides the admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
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

// Get staging status
$staging_instance = new WP_Easy_Staging_Staging();
$status = $staging_instance->get_staging_status();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wp-easy-staging-dashboard">
        <div class="wp-easy-staging-card">
            <h2><?php _e('Staging Environment', 'wp-easy-staging'); ?></h2>
            
            <?php if ($status['has_staging']) : ?>
                <div class="wp-easy-staging-status wp-easy-staging-status-active">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Active', 'wp-easy-staging'); ?>
                </div>
                
                <div class="wp-easy-staging-info">
                    <p><strong><?php _e('Name:', 'wp-easy-staging'); ?></strong> <?php echo esc_html($status['staging']['name']); ?></p>
                    <p><strong><?php _e('URL:', 'wp-easy-staging'); ?></strong> <a href="<?php echo esc_url($status['staging']['staging_url']); ?>" target="_blank"><?php echo esc_html($status['staging']['staging_url']); ?></a></p>
                    
                    <?php if (defined('WP_EASY_STAGING_DOCKER_DEV') && WP_EASY_STAGING_DOCKER_DEV): ?>
                    <div class="wp-easy-staging-dev-note">
                        <p><strong><?php _e('Development Mode Active', 'wp-easy-staging'); ?></strong></p>
                        <p><?php _e('You are running in Docker development mode. The staging URL above will not work directly.', 'wp-easy-staging'); ?></p>
                        <p><?php _e('In development mode, the plugin simulates staging environment creation without setting up actual URL routing.', 'wp-easy-staging'); ?></p>
                        <p><?php _e('Database tables with the staging prefix have been created and can be used for testing push functionality.', 'wp-easy-staging'); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <p><strong><?php _e('Created:', 'wp-easy-staging'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($status['staging']['date_created']))); ?></p>
                    <p><strong><?php _e('Pending Changes:', 'wp-easy-staging'); ?></strong> <?php echo esc_html($status['changes_count']); ?></p>
                </div>
                
                <div class="wp-easy-staging-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging-push')); ?>" class="button button-primary"><?php _e('Push to Production', 'wp-easy-staging'); ?></a>
                    <button type="button" class="button button-link-delete wp-easy-staging-delete" data-id="<?php echo esc_attr($status['staging']['id']); ?>">
                        <?php _e('Delete Staging', 'wp-easy-staging'); ?>
                    </button>
                </div>
            <?php else : ?>
                <div class="wp-easy-staging-status wp-easy-staging-status-inactive">
                    <span class="dashicons dashicons-no-alt"></span>
                    <?php _e('Inactive', 'wp-easy-staging'); ?>
                </div>
                
                <p><?php _e('No staging environment found. Create one to get started.', 'wp-easy-staging'); ?></p>
                
                <div class="wp-easy-staging-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-easy-staging-create')); ?>" class="button button-primary"><?php _e('Create Staging', 'wp-easy-staging'); ?></a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="wp-easy-staging-card">
            <h2><?php _e('How It Works', 'wp-easy-staging'); ?></h2>
            
            <div class="wp-easy-staging-steps">
                <div class="wp-easy-staging-step">
                    <span class="wp-easy-staging-step-number">1</span>
                    <h3><?php _e('Create Staging', 'wp-easy-staging'); ?></h3>
                    <p><?php _e('Create a clone of your production site in a safe staging environment.', 'wp-easy-staging'); ?></p>
                </div>
                
                <div class="wp-easy-staging-step">
                    <span class="wp-easy-staging-step-number">2</span>
                    <h3><?php _e('Make Changes', 'wp-easy-staging'); ?></h3>
                    <p><?php _e('Test new plugins, themes, content changes, and more without affecting your live site.', 'wp-easy-staging'); ?></p>
                </div>
                
                <div class="wp-easy-staging-step">
                    <span class="wp-easy-staging-step-number">3</span>
                    <h3><?php _e('Push to Production', 'wp-easy-staging'); ?></h3>
                    <p><?php _e('Once tested, push your changes to the live site with a single click.', 'wp-easy-staging'); ?></p>
                </div>
                
                <div class="wp-easy-staging-step">
                    <span class="wp-easy-staging-step-number">4</span>
                    <h3><?php _e('Resolve Conflicts', 'wp-easy-staging'); ?></h3>
                    <p><?php _e('If conflicts are detected, the plugin will help you resolve them manually.', 'wp-easy-staging'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($status['has_staging']) : ?>
        <div class="wp-easy-staging-card wp-easy-staging-notes">
            <h2><?php _e('Important Notes', 'wp-easy-staging'); ?></h2>
            
            <?php if (defined('WP_EASY_STAGING_DOCKER_DEV') && WP_EASY_STAGING_DOCKER_DEV): ?>
            <div class="wp-easy-staging-dev-note">
                <p><strong><?php _e('Docker Development Mode Active', 'wp-easy-staging'); ?></strong></p>
                <p><?php _e('In development mode, staging sites are simulated without actual web server configuration:', 'wp-easy-staging'); ?></p>
                <ul>
                    <li><?php _e('The staging URL will not be accessible directly', 'wp-easy-staging'); ?></li>
                    <li><?php _e('Database tables with staging prefix are created for testing', 'wp-easy-staging'); ?></li>
                    <li><?php _e('You can still test Push to Production functionality', 'wp-easy-staging'); ?></li>
                    <li><?php _e('For a full implementation, use this plugin in a non-Docker environment', 'wp-easy-staging'); ?></li>
                </ul>
            </div>
            <?php endif; ?>
            
            <ul>
                <li><?php _e('Your staging site is located at:', 'wp-easy-staging'); ?> <strong><?php echo esc_html($status['staging']['staging_url']); ?></strong></li>
                <li><?php _e('Any changes you make on the staging site will not affect your live site until you push them.', 'wp-easy-staging'); ?></li>
                <li><?php _e('If your staging site becomes out of sync with your live site, you can delete it and create a new one.', 'wp-easy-staging'); ?></li>
                <li><?php _e('When pushing changes, you can select which changes to push and which to ignore.', 'wp-easy-staging'); ?></li>
                <li><?php _e('In case of conflicts, the plugin will help you resolve them before pushing.', 'wp-easy-staging'); ?></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<style>
    .wp-easy-staging-dashboard {
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
        flex: 1;
        min-width: 300px;
    }
    
    .wp-easy-staging-status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 3px;
        margin-bottom: 15px;
        font-weight: bold;
    }
    
    .wp-easy-staging-status-active {
        background-color: #dff0d8;
        color: #3c763d;
    }
    
    .wp-easy-staging-status-inactive {
        background-color: #f2dede;
        color: #a94442;
    }
    
    .wp-easy-staging-info {
        margin-bottom: 15px;
    }
    
    .wp-easy-staging-actions {
        margin-top: 15px;
    }
    
    .wp-easy-staging-steps {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .wp-easy-staging-step {
        flex: 1;
        min-width: 200px;
        position: relative;
        padding-left: 40px;
    }
    
    .wp-easy-staging-step-number {
        position: absolute;
        left: 0;
        top: 0;
        width: 30px;
        height: 30px;
        background-color: #2271b1;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .wp-easy-staging-step h3 {
        margin-top: 0;
        margin-bottom: 5px;
    }
    
    .wp-easy-staging-step p {
        margin-top: 0;
    }
    
    .wp-easy-staging-notes {
        margin-top: 20px;
    }
    
    .wp-easy-staging-notes ul {
        list-style-type: disc;
        padding-left: 20px;
    }
    
    .wp-easy-staging-dev-note {
        background-color: #fef8ee;
        border-left: 4px solid #f0b849;
        padding: 12px;
        margin: 15px 0;
        border-radius: 4px;
    }
    
    .wp-easy-staging-dev-note p {
        margin: 5px 0;
    }
    
    .wp-easy-staging-dev-note p:first-child {
        margin-top: 0;
        color: #b45a00;
        font-weight: 600;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        $('.wp-easy-staging-delete').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('<?php _e('Are you sure you want to delete this staging site? This action cannot be undone.', 'wp-easy-staging'); ?>')) {
                const stagingId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wp_easy_staging_delete_staging',
                        id: stagingId,
                        nonce: '<?php echo wp_create_nonce('wp_easy_staging_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred. Please try again.', 'wp-easy-staging'); ?>');
                    }
                });
            }
        });
    });
</script> 