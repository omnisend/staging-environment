# WP Easy Staging

A free, open-source WordPress plugin that allows users to create staging environments, make changes, and push those changes back to production with conflict resolution.

## Features

- **One-Click Staging**: Create staging environments with a single click
- **Safe Testing**: Test changes without affecting your live site
- **Conflict Detection**: Intelligent detection of conflicts between staging and production
- **Selective Push**: Choose which changes to push to production
- **Database & File Sync**: Synchronize both database and file changes
- **Change Logging**: Comprehensive logging of all changes made in staging

## Development Environment

This repository includes a Docker-based development environment to make it easy to develop and test the WP Easy Staging plugin.

### Requirements

- [Docker](https://www.docker.com/products/docker-desktop/)
- [Docker Compose](https://docs.docker.com/compose/install/)

### Getting Started

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/wp-easy-staging.git
   cd wp-easy-staging
   ```

2. Start the development environment:
   ```
   ./dev.sh start
   ```

   This will start:
   - WordPress container at http://localhost:8000
   - MySQL database
   - phpMyAdmin at http://localhost:8080
   - WP-CLI container for WordPress management

3. Install WordPress using the helper script:
   ```
   ./dev.sh install-wp
   ```

4. Activate the plugin:
   ```
   ./dev.sh activate
   ```

5. Access WordPress admin:
   - URL: http://localhost:8000/wp-admin
   - Username: admin
   - Password: password

### Development Helper Script

The included `dev.sh` script provides several commands to make development easier:

```
Usage: ./dev.sh [command]

Available commands:
  start        - Start Docker containers
  stop         - Stop Docker containers
  restart      - Restart Docker containers
  reset        - Reset Docker environment (removes volumes)
  logs         - Display WordPress logs
  shell        - Open shell in WordPress container
  install-wp   - Install WordPress using WP-CLI
  wp           - Run WP-CLI commands (e.g. ./dev.sh wp plugin list)
  activate     - Activate WP Easy Staging plugin
  deactivate   - Deactivate WP Easy Staging plugin
  help         - Display this help message
```

Examples:
```
./dev.sh wp plugin list
./dev.sh wp user list
./dev.sh wp db export backup.sql
```

### Directory Structure

The plugin code is located in the `wp-easy-staging` directory and is mounted directly into the WordPress container, so any changes you make will be immediately reflected.

```
wp-easy-staging/
├── admin/             # Admin-specific functionality
│   ├── css/           # Admin stylesheets
│   ├── js/            # Admin JavaScript
│   └── partials/      # Admin templates
├── includes/          # Core plugin functionality
│   ├── core/          # Core classes
│   ├── db/            # Database operations
│   └── utils/         # Utility functions
├── languages/         # Internationalization
├── public/            # Public-facing functionality
└── wp-easy-staging.php # Main plugin file
```

### Debugging

To enable WordPress debugging:

- Debugging is already enabled in the development environment
- Logs are stored in the WordPress container
- View logs with `./dev.sh logs`

### How It Works

The development environment:

1. Sets up a WordPress installation with the necessary database
2. Mounts the plugin code directly into the WordPress plugins directory
3. Provides tools for managing WordPress and the plugin development
4. Changes made to the plugin code are immediately reflected in the WordPress instance

### Stopping and Resetting

- To stop the containers: `./dev.sh stop`
- To reset everything (including database): `./dev.sh reset`

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the LICENSE file for details. 