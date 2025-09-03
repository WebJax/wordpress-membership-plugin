<?php
/**
 * Plugin Name:       Membership Manager
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Manage memberships and subscriptions.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Gemini CLI / Gemini Pro 2.5
 * Author URI:        https://gemini.jaxweb.dk/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       membership-manager
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include the main class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-membership-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-membership-emails.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-membership-renewals.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-membership-admin.php';


// Initialize the plugin.
Membership_Manager::init();
new Membership_Admin();

// Activation hook.
register_activation_hook( __FILE__, array( 'Membership_Manager', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Membership_Manager', 'deactivate' ) );
