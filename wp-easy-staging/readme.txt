=== WP Easy Staging ===
Contributors: cloudfest
Tags: staging, development, clone, migrate, sync, conflict resolution
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Create staging sites, make changes, and push back to production with conflict resolution.

== Description ==

WP Easy Staging is a free, open-source WordPress plugin that allows you to quickly create staging environments, make changes, and push them back to production. Unlike other staging plugins, WP Easy Staging includes a powerful conflict resolution system that detects and allows you to manually resolve conflicts when pushing changes back to production.

= Key Features =

* **One-click Staging Environment Creation**: Create a complete copy of your WordPress site in seconds.
* **Safe Testing Environment**: Make changes to your staging site without affecting your live site.
* **Push Changes to Production**: Once you're satisfied with your changes, push them back to your live site.
* **Conflict Detection & Resolution**: The plugin detects conflicts between staging and production and provides a user-friendly interface to resolve them.
* **Selective Push**: Choose which changes to push back to production.
* **Database & File Synchronization**: Sync both database changes and file changes.
* **Change Logging**: Track all changes made in the staging environment for easier review.

= Use Cases =

* Test new plugins before installing them on your live site
* Experiment with theme changes
* Update content without affecting your live site
* Collaborate with team members on site changes
* Test WordPress updates before applying them to production

= Why Choose WP Easy Staging =

While there are other staging solutions available, WP Easy Staging stands out with its powerful conflict resolution capability. When changes have been made to both staging and production, other solutions might overwrite one set of changes. WP Easy Staging detects these conflicts and gives you a user-friendly interface to choose which changes to keep, merge changes, or create a custom solution.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-easy-staging` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to 'WP Easy Staging' in your admin menu to create your first staging site.

== Frequently Asked Questions ==

= Is this plugin free? =

Yes, WP Easy Staging is completely free and open-source.

= Will creating a staging site affect my live site? =

No, the staging site is created as a separate copy of your site. Your live site will continue to function normally while you work on the staging site.

= How long does it take to create a staging site? =

The time it takes depends on the size of your site, but WP Easy Staging is optimized for performance. Small to medium-sized sites typically take less than a minute.

= What happens if there are conflicts when pushing changes? =

WP Easy Staging detects conflicts and provides a user-friendly interface to resolve them. You can choose to keep the staging version, keep the production version, or create a custom merged solution.

= Can I create multiple staging sites? =

Yes, you can create multiple staging sites for different purposes.

= Does this plugin work with all themes and plugins? =

WP Easy Staging is designed to be compatible with most WordPress themes and plugins. However, plugins that make significant changes to the WordPress database structure might cause issues.

== Screenshots ==

1. Dashboard with staging site overview
2. Creating a new staging site
3. Pushing changes to production
4. Conflict resolution interface
5. Selective push options

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial version of WP Easy Staging.

== Documentation ==

For detailed documentation, please visit the [WP Easy Staging GitHub repository](https://github.com/omnisend/wp-easy-staging). 