<?php
/**
 * Membership Test Tools
 * Handles testing functionality for renewals and emails
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Test_Tools {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_test_tools_menu' ) );
        add_action( 'admin_post_test_reminder_emails', array( $this, 'handle_test_reminder_emails' ) );
        add_action( 'admin_post_test_automatic_renewal', array( $this, 'handle_test_automatic_renewal' ) );
        add_action( 'admin_post_run_renewal_process', array( $this, 'handle_run_renewal_process' ) );
    }

    /**
     * Add test tools submenu page
     */
    public function add_test_tools_menu() {
        add_submenu_page(
            'membership-manager',
            __( 'Test Tools', 'membership-manager' ),
            __( 'Test Tools', 'membership-manager' ),
            'manage_options',
            'membership-test-tools',
            array( $this, 'render_test_tools_page' )
        );
    }

    /**
     * Render test tools page
     */
    public function render_test_tools_page() {
        $template_path = plugin_dir_path( __FILE__ ) . '../admin/views/test-tools-page.php';
        // Validate the path is within plugin directory
        $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
        if ( strpos( realpath( $template_path ), realpath( $plugin_dir ) ) === 0 && file_exists( $template_path ) ) {
            include_once $template_path;
        } else {
            wp_die( __( 'Template file not found or invalid path.', 'membership-manager' ) );
        }
    }

    /**
     * Handle test reminder emails submission
     */
    public function handle_test_reminder_emails() {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'test_reminder_emails_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $test_email = isset( $_POST['test_email_address'] ) ? sanitize_email( $_POST['test_email_address'] ) : '';
        $reminder_type = isset( $_POST['reminder_type'] ) ? sanitize_text_field( $_POST['reminder_type'] ) : 'all';
        $renewal_type_test = isset( $_POST['renewal_type_test'] ) ? sanitize_text_field( $_POST['renewal_type_test'] ) : 'both';

        if ( ! is_email( $test_email ) ) {
            wp_die( __( 'Invalid email address.', 'membership-manager' ) );
        }

        Membership_Manager::log( sprintf( __( 'Starting test reminder email process. Target: %s, Type: %s, Renewal: %s', 'membership-manager' ), $test_email, $reminder_type, $renewal_type_test ) );

        $results = array();
        $reminder_types = array();

        // Determine which reminder types to test
        if ( $reminder_type === 'all' ) {
            $reminder_types = array( '30_days', '14_days', '7_days', '1_day' );
        } else {
            $reminder_types = array( $reminder_type );
        }

        // Determine which renewal types to test
        $renewal_types = array();
        if ( $renewal_type_test === 'both' ) {
            $renewal_types = array( 'automatic', 'manual' );
        } else {
            $renewal_types = array( $renewal_type_test );
        }

        // Create a test subscription object
        $current_user = wp_get_current_user();
        $emails = new Membership_Emails();

        // Hook into wp_mail to redirect emails to test address
        $email_redirect_filter = function( $args ) use ( $test_email ) {
            $args['to'] = $test_email;
            return $args;
        };
        add_filter( 'wp_mail', $email_redirect_filter );

        foreach ( $renewal_types as $renewal_type ) {
            foreach ( $reminder_types as $type ) {
                // Create a fake subscription object for testing
                $test_subscription = (object) array(
                    'id' => 99999,
                    'user_id' => $current_user->ID,
                    'renewal_type' => $renewal_type,
                    'status' => 'active',
                    'start_date' => date( 'Y-m-d H:i:s', strtotime( '-11 months' ) ),
                    'end_date' => $this->calculate_test_end_date( $type ),
                    'renewal_token' => 'test-token-' . uniqid()
                );

                // Send the test email
                if ( $renewal_type === 'automatic' ) {
                    $emails->send_automatic_renewal_reminders( $test_subscription, $type );
                    $label = sprintf( __( 'Automatic Renewal - %s', 'membership-manager' ), $this->get_reminder_label( $type ) );
                } else {
                    $emails->send_manual_renewal_reminders( $test_subscription, $type );
                    $label = sprintf( __( 'Manual Renewal - %s', 'membership-manager' ), $this->get_reminder_label( $type ) );
                }

                $results[] = $label;
                Membership_Manager::log( sprintf( __( 'Sent test email: %s to %s', 'membership-manager' ), $label, $test_email ) );
            }
        }

        // Remove the filter
        remove_filter( 'wp_mail', $email_redirect_filter );

        Membership_Manager::log( sprintf( __( 'Test reminder email process completed. Sent %d emails.', 'membership-manager' ), count( $results ) ) );

        // Redirect with success message
        $redirect_url = add_query_arg(
            array(
                'page' => 'membership-test-tools',
                'test_emails_sent' => count( $results ),
                'target_email' => urlencode( $test_email )
            ),
            admin_url( 'admin.php' )
        );

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle test automatic renewal submission
     */
    public function handle_test_automatic_renewal() {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'test_automatic_renewal_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
        $force_renewal = isset( $_POST['force_renewal'] ) && $_POST['force_renewal'] === 'yes';

        if ( ! $subscription_id ) {
            wp_die( __( 'Invalid subscription ID.', 'membership-manager' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        $subscription = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}membership_subscriptions` WHERE id = %d",
            $subscription_id
        ) );

        if ( ! $subscription ) {
            wp_die( __( 'Subscription not found.', 'membership-manager' ) );
        }

        Membership_Manager::log( sprintf( __( 'Starting test automatic renewal for subscription ID: %d (Force: %s)', 'membership-manager' ), $subscription_id, $force_renewal ? 'Yes' : 'No' ) );

        // Check if subscription is automatic renewal type
        if ( $subscription->renewal_type !== 'automatic' && ! $force_renewal ) {
            wp_die( __( 'This membership is not set for automatic renewal. Check "Force Renewal" to test anyway.', 'membership-manager' ) );
        }

        // Create renewal order
        $renewals = new Membership_Renewals();
        $order_id = $renewals->create_renewal_order( $subscription );

        if ( $order_id ) {
            Membership_Manager::log( sprintf( __( 'Test automatic renewal successful. Created order #%d', 'membership-manager' ), $order_id ) );

            // Redirect with success message
            $redirect_url = add_query_arg(
                array(
                    'page' => 'membership-test-tools',
                    'renewal_order_created' => $order_id,
                    'subscription_id' => $subscription_id
                ),
                admin_url( 'admin.php' )
            );
        } else {
            Membership_Manager::log( sprintf( __( 'Test automatic renewal failed for subscription ID: %d', 'membership-manager' ), $subscription_id ), 'ERROR' );

            // Redirect with error message
            $redirect_url = add_query_arg(
                array(
                    'page' => 'membership-test-tools',
                    'renewal_failed' => 1,
                    'subscription_id' => $subscription_id
                ),
                admin_url( 'admin.php' )
            );
        }

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle run renewal process submission
     */
    public function handle_run_renewal_process() {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'run_renewal_process_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        Membership_Manager::log( __( 'Manually triggered full renewal process from test tools.', 'membership-manager' ) );

        // Run the full renewal process
        Membership_Manager::run_renewal_process();

        Membership_Manager::log( __( 'Manual renewal process completed.', 'membership-manager' ) );

        // Redirect with success message
        $redirect_url = add_query_arg(
            array(
                'page' => 'membership-test-tools',
                'renewal_process_run' => 1,
                'view_logs' => 1
            ),
            admin_url( 'admin.php' )
        );

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Calculate test end date based on reminder type
     *
     * @param string $reminder_type The reminder type (30_days, 14_days, 7_days, 1_day)
     * @return string The calculated end date
     */
    private function calculate_test_end_date( $reminder_type ) {
        $days_map = array(
            '30_days' => 30,
            '14_days' => 14,
            '7_days' => 7,
            '1_day' => 1
        );

        $days = isset( $days_map[ $reminder_type ] ) ? $days_map[ $reminder_type ] : 30;

        return date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
    }

    /**
     * Get human-readable reminder label
     *
     * @param string $reminder_type The reminder type
     * @return string The label
     */
    private function get_reminder_label( $reminder_type ) {
        $labels = array(
            '30_days' => __( '30 Days Before Expiration', 'membership-manager' ),
            '14_days' => __( '14 Days Before Expiration', 'membership-manager' ),
            '7_days' => __( '7 Days Before Expiration', 'membership-manager' ),
            '1_day' => __( '1 Day Before Expiration', 'membership-manager' )
        );

        return isset( $labels[ $reminder_type ] ) ? $labels[ $reminder_type ] : $reminder_type;
    }
}
