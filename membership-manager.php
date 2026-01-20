<?php
/**
 * Plugin Name:       JW Membership Manager
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Manage memberships and subscriptions.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Jaxweb + AI
 * Author URI:        https://jaxweb.dk/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       membership-manager
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'MEMBERSHIP_MANAGER_VERSION', '1.0.0' );
define( 'MEMBERSHIP_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEMBERSHIP_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Staging Mode
 * 
 * Enable staging mode to prevent:
 * - Automatic renewals from running
 * - Emails from being sent
 * - Real payment processing
 * 
 * To enable, add to wp-config.php:
 * define( 'MEMBERSHIP_STAGING_MODE', true );
 */
if ( ! defined( 'MEMBERSHIP_STAGING_MODE' ) ) {
    define( 'MEMBERSHIP_STAGING_MODE', false );
}

// Include the main classes
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-constants.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-utils.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-security.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-manager.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-emails.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-renewals.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-admin.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-shortcodes.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-roles.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-dashboard.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-product-types.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-checkout.php';
require_once MEMBERSHIP_MANAGER_PLUGIN_DIR . 'includes/class-membership-test-tools.php';

// Initialize the plugin.
Membership_Manager::init();
new Membership_Admin();
Membership_Shortcodes::init();
Membership_Roles::init();
Membership_Dashboard::init();
Membership_Product_Types::init();
Membership_Checkout::init();
new Membership_Test_Tools();

// Show staging mode notice
if ( MEMBERSHIP_STAGING_MODE ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-warning" style="border-left-color: #f0b849;">';
        echo '<p><strong>' . __( '⚠️ STAGING MODE ACTIVE', 'membership-manager' ) . '</strong> - ';
        echo __( 'Automatic renewals and emails are disabled. To disable staging mode, remove MEMBERSHIP_STAGING_MODE from wp-config.php', 'membership-manager' );
        echo '</p></div>';
    });
}

// Activation hook.
register_activation_hook( __FILE__, array( 'Membership_Manager', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Membership_Manager', 'deactivate' ) );
