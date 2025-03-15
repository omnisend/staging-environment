#!/bin/bash
# Development helper script for WP Easy Staging plugin

# Colors for better terminal output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Print a formatted header
print_header() {
    echo ""
    echo -e "${CYAN}======================================================${NC}"
    echo -e "${CYAN}==== ${YELLOW}$1${CYAN} ====${NC}"
    echo -e "${CYAN}======================================================${NC}"
    echo ""
}

# WP-CLI command wrapper - using WordPress container
run_wp_cli() {
    docker compose exec wordpress wp --allow-root "$@"
}

# Wait for WordPress to be ready
wait_for_wordpress() {
    print_header "Waiting for WordPress to be ready"
    
    # Wait for MySQL to be ready
    echo -e "${YELLOW}Waiting for MySQL to be ready...${NC}"
    for i in {1..30}; do
        if docker compose exec db mysqladmin ping -h localhost -u wordpress -pwordpress --silent; then
            echo -e "${GREEN}MySQL is ready!${NC}"
            break
        fi
        echo -n "."
        sleep 1
    done
    
    # Wait for WordPress
    echo -e "${YELLOW}Waiting for WordPress to be ready...${NC}"
    for i in {1..30}; do
        if curl -s http://localhost:8000 > /dev/null; then
            echo -e "${GREEN}WordPress is ready!${NC}"
            return 0
        fi
        echo -n "."
        sleep 1
    done
    
    echo -e "${RED}Timed out waiting for WordPress to be ready.${NC}"
    return 1
}

# Ensure plugin directory exists
ensure_plugin_directory() {
    if [ ! -d "wp-easy-staging" ]; then
        # Let's create the directory structure
        echo -e "${YELLOW}wp-easy-staging directory not found. Creating it...${NC}"
        mkdir -p wp-easy-staging
        if [ $? -ne 0 ]; then
            echo -e "${RED}Error: Could not create wp-easy-staging directory.${NC}"
            exit 1
        fi
    fi
}

# Display WordPress and phpMyAdmin URLs
show_urls() {
    echo -e "📲 ${GREEN}WordPress URL: ${BLUE}http://localhost:8000${NC}"
    echo -e "🔧 ${GREEN}phpMyAdmin URL: ${BLUE}http://localhost:8080${NC}"
    echo -e "🔑 ${GREEN}WordPress Admin: ${BLUE}http://localhost:8000/wp-admin${NC}"
    echo -e "👤 ${GREEN}Default credentials: ${YELLOW}admin / password${NC} (if setup manually)"
}

# Help command
show_help() {
    print_header "WP Easy Staging Development Helper"
    echo -e "${GREEN}Usage:${NC} ./dev.sh [command]"
    echo ""
    echo -e "${YELLOW}Available commands:${NC}"
    echo -e "  ${CYAN}start${NC}        - Start Docker containers"
    echo -e "  ${CYAN}stop${NC}         - Stop Docker containers"
    echo -e "  ${CYAN}restart${NC}      - Restart Docker containers"
    echo -e "  ${CYAN}reset${NC}        - Reset Docker environment (removes volumes)"
    echo -e "  ${CYAN}logs${NC}         - Display WordPress logs"
    echo -e "  ${CYAN}shell${NC}        - Open shell in WordPress container"
    echo -e "  ${CYAN}wp${NC}           - Run WP-CLI commands (e.g. ./dev.sh wp plugin list)"
    echo -e "  ${CYAN}activate${NC}     - Activate WP Easy Staging plugin"
    echo -e "  ${CYAN}deactivate${NC}   - Deactivate WP Easy Staging plugin"
    echo -e "  ${CYAN}help${NC}         - Display this help message"
    echo ""
    echo -e "${YELLOW}Examples:${NC}"
    echo -e "  ${CYAN}./dev.sh start${NC}"
    echo -e "  ${CYAN}./dev.sh wp plugin list${NC}"
    echo -e "  ${CYAN}./dev.sh wp user list${NC}"
}

# Check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        echo -e "${RED}Error: Docker is not running. Please start Docker first.${NC}"
        exit 1
    fi
}

# Check command line arguments
if [ $# -eq 0 ]; then
    show_help
    exit 0
fi

# Parse command
case "$1" in
    start)
        check_docker
        ensure_plugin_directory
        print_header "Starting Docker Containers"
        docker compose up -d
        wait_for_wordpress
        echo -e "${GREEN}Containers started successfully!${NC}"
        show_urls
        ;;

    stop)
        check_docker
        print_header "Stopping Docker Containers"
        docker compose down
        echo -e "${GREEN}Containers stopped successfully!${NC}"
        ;;

    restart)
        check_docker
        ensure_plugin_directory
        print_header "Restarting Docker Containers"
        docker compose down
        docker compose up -d
        wait_for_wordpress
        echo -e "${GREEN}Containers restarted successfully!${NC}"
        show_urls
        ;;

    reset)
        check_docker
        ensure_plugin_directory
        print_header "Resetting Docker Environment"
        docker compose down -v
        echo -e "${YELLOW}All volumes removed.${NC}"
        docker compose up -d
        wait_for_wordpress
        echo -e "${GREEN}Environment reset successfully!${NC}"
        show_urls
        ;;

    logs)
        check_docker
        print_header "WordPress Logs"
        docker compose logs -f wordpress
        ;;

    shell)
        check_docker
        print_header "Opening Shell in WordPress Container"
        docker compose exec wordpress bash
        ;;

    wp)
        check_docker
        shift
        run_wp_cli "$@"
        ;;

    activate)
        check_docker
        print_header "Activating WP Easy Staging Plugin"
        run_wp_cli plugin activate wp-easy-staging
        echo -e "${GREEN}Plugin activated successfully!${NC}"
        ;;

    deactivate)
        check_docker
        print_header "Deactivating WP Easy Staging Plugin"
        run_wp_cli plugin deactivate wp-easy-staging
        echo -e "${GREEN}Plugin deactivated successfully!${NC}"
        ;;

    help)
        show_help
        ;;

    *)
        echo -e "${RED}Unknown command: $1${NC}"
        show_help
        exit 1
        ;;
esac

exit 0 