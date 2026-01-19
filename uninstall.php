<?php
/**
 * Uninstall handler for Membership Manager
 * 
 * This file is executed when the plugin is deleted through WordPress admin.
 * It cleans up all plugin data including database tables, options, and user meta.
 */

// Exit if accessed directly or not uninstalling
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove custom database table
$table_name = $wpdb->prefix . 'membership_subscriptions';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Remove plugin options
$options = array(
    'membership_automatic_renewal_products',
    'membership_manual_renewal_products',
    'membership_member_role',
    'membership_remove_role_on_expiration',
    'membership_email_from_name',
    'membership_email_from_address',
    'membership_enable_reminders',
    'membership_reminder_30_subject',
    'membership_reminder_14_subject',
    'membership_reminder_7_subject',
    'membership_reminder_1_subject',
    'membership_enable_welcome_email',
    'membership_welcome_subject',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove user meta
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'has_active_membership'" );

// Clear scheduled hooks
wp_clear_scheduled_hook( 'membership_renewal_cron' );

// Clear rewrite rules
flush_rewrite_rules();

// Log uninstallation
$log_file = plugin_dir_path( __FILE__ ) . 'logs/membership.log';
if ( file_exists( $log_file ) ) {
    $timestamp = date( 'Y-m-d H:i:s' );
    $log_message = "[$timestamp] [INFO] - Plugin uninstalled and all data removed." . PHP_EOL;
    file_put_contents( $log_file, $log_message, FILE_APPEND );
}
