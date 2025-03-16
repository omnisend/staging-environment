# Staging2Live

Website: https://staging2live.com

## Overview

**Staging2Live** is a WordPress plugin designed to simplify the process of setting up a staging environment. It ensures that staging environments are configured securely, preventing issues like the corruption of production data or broken integrations. By automating configuration and providing clear visual guidance directly within the WordPress Admin, the tool helps developers and merchants create reliable, secure, and effective staging environments.

### Key Features

- **Staging Environment Validation**: Automatically checks if staging environment configurations (like `WP_ENVIRONMENT_TYPE`) are correctly set up, ensuring a smooth transition between production and staging. It also ensures that live emails are disabled in staging environments.

- **Automated Selective Content Sync**: Offers a safe, automated method for syncing content from production to staging, with anonymized user data to protect privacy.

- **Visual Alerts and Guidance**: Provides clear prompts and visual alerts within WordPress Admin to guide users during setup, ensuring that all configurations are properly set and minimizing the risk of manual errors.

- **Long-Term Expandability**: Designed to be extensible, this tool supports future plugins and integrations beyond WooCommerce through hooks and custom settings, allowing developers to easily extend its functionality for various needs.

## Target Audience

- **WordPress Merchants**: Especially those using WooCommerce and Omnisend, who often face issues with improperly configured staging environments.

- **Developers**: Professionals who need automated solutions for staging setup to avoid the pitfalls of manual configuration and errors.

- **Hosting Providers**: Companies offering WordPress hosting solutions who are looking for tools to help streamline the staging process for their customers.

## Installation

1. Download the plugin here from GitHub [here](https://github.com/omnisend/staging2live).
2. Upload the plugin folder to your `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in the WordPress dashboard.
4. Once activated, the tool will appear under the "Staging2Live" menu in the WordPress Admin dashboard.

## Features and Usage

### 1. Environment Configuration Validation
The plugin checks critical staging environment settings and ensures that key parameters like `WP_ENVIRONMENT_TYPE` are correctly configured. If any issues are detected (e.g., live emails not disabled in staging), the plugin will provide alerts and suggested fixes.

### 2. Automated Content Sync
The plugin allows for safe, automated synchronization of selected content (e.g., posts, pages, media) from your production environment to staging. Anonymization ensures that sensitive user data (such as email addresses) is protected.

### 3. Visual Alerts and Prompts
Throughout the staging setup process, the plugin displays visual alerts in the WordPress Admin dashboard. These prompts guide users to complete key actions, ensuring proper configuration before deploying to the staging environment.

## Contributing

We welcome contributions to this project! If you would like to contribute, please follow these steps:

1. Fork the repository.
2. Create a new branch (`git checkout -b feature-branch`).
3. Commit your changes (`git commit -am 'Add feature'`).
4. Push to the branch (`git push origin feature-branch`).
5. Open a pull request.

## License

This project is licensed under the GPL - see the [LICENSE](LICENSE) file for details.

## Support

If you encounter any issues or have questions, feel free to open an issue.

---

By using **Staging2Live**, you can streamline your staging environment setup and ensure a secure, reliable process for your development and staging workflows.
