<?php
/**
 * Test Tools Admin Page
 * Provides testing interface for membership renewals and email reminders
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'membership_subscriptions';

// Get all active subscriptions for testing
$active_subscriptions = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'active' ORDER BY end_date ASC LIMIT 20" );

?>
<div class="wrap">
    <h1><?php _e( 'Membership Test Tools', 'membership-manager' ); ?></h1>
    <p class="description"><?php _e( 'Use these tools to test automatic renewal functionality and reminder email delivery.', 'membership-manager' ); ?></p>

    <?php
    // Display success/error messages
    if ( isset( $_GET['test_emails_sent'] ) ) {
        $count = absint( $_GET['test_emails_sent'] );
        $email = isset( $_GET['target_email'] ) ? urldecode( $_GET['target_email'] ) : '';
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo sprintf( __( 'Successfully sent %d test reminder email(s) to %s. Check your inbox and spam folder.', 'membership-manager' ), $count, esc_html( $email ) );
        echo '</p></div>';
    }

    if ( isset( $_GET['renewal_order_created'] ) ) {
        $order_id = absint( $_GET['renewal_order_created'] );
        $subscription_id = isset( $_GET['subscription_id'] ) ? absint( $_GET['subscription_id'] ) : 0;
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo sprintf( 
            __( 'Successfully created test renewal order #%d for subscription ID %d. <a href="%s" target="_blank">View Order</a>', 'membership-manager' ),
            $order_id,
            $subscription_id,
            admin_url( 'post.php?post=' . $order_id . '&action=edit' )
        );
        echo '</p></div>';
    }

    if ( isset( $_GET['renewal_failed'] ) ) {
        $subscription_id = isset( $_GET['subscription_id'] ) ? absint( $_GET['subscription_id'] ) : 0;
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo sprintf( 
            __( 'Failed to create renewal order for subscription ID %d. Check the logs below for details.', 'membership-manager' ),
            $subscription_id
        );
        echo '</p></div>';
    }

    if ( isset( $_GET['renewal_process_run'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo __( 'Successfully ran the full renewal process. Check the logs below for details.', 'membership-manager' );
        echo '</p></div>';
    }
    ?>

    <!-- Test Reminder Emails Section -->
    <div class="membership-test-section">
        <h2><?php _e( 'Test Reminder Emails', 'membership-manager' ); ?></h2>
        <p><?php _e( 'Send test reminder emails to verify that the email system is working correctly with all reminder intervals.', 'membership-manager' ); ?></p>
        
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top: 20px;">
            <input type="hidden" name="action" value="test_reminder_emails">
            <?php wp_nonce_field( 'test_reminder_emails_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_email_address"><?php _e( 'Email Address', 'membership-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="email" id="test_email_address" name="test_email_address" class="regular-text" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" required>
                        <p class="description"><?php _e( 'Enter the email address where test emails should be sent.', 'membership-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="reminder_type"><?php _e( 'Reminder Type', 'membership-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="reminder_type" name="reminder_type" class="regular-text">
                            <option value="all"><?php _e( 'All Reminders (30, 14, 7, 1 days)', 'membership-manager' ); ?></option>
                            <option value="30_days"><?php _e( '30 Days Before Expiration', 'membership-manager' ); ?></option>
                            <option value="14_days"><?php _e( '14 Days Before Expiration', 'membership-manager' ); ?></option>
                            <option value="7_days"><?php _e( '7 Days Before Expiration', 'membership-manager' ); ?></option>
                            <option value="1_day"><?php _e( '1 Day Before Expiration', 'membership-manager' ); ?></option>
                        </select>
                        <p class="description"><?php _e( 'Select which reminder email to test.', 'membership-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="renewal_type_test"><?php _e( 'Renewal Type', 'membership-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="renewal_type_test" name="renewal_type_test" class="regular-text">
                            <option value="both"><?php _e( 'Both (Manual & Automatic)', 'membership-manager' ); ?></option>
                            <option value="automatic"><?php _e( 'Automatic Renewal', 'membership-manager' ); ?></option>
                            <option value="manual"><?php _e( 'Manual Renewal', 'membership-manager' ); ?></option>
                        </select>
                        <p class="description"><?php _e( 'Test emails for specific renewal type.', 'membership-manager' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button( __( 'Send Test Reminder Emails', 'membership-manager' ), 'primary', 'submit', false ); ?>
        </form>
    </div>

    <hr style="margin: 40px 0;">

    <!-- Test Automatic Renewal Section -->
    <div class="membership-test-section">
        <h2><?php _e( 'Test Automatic Renewal Process', 'membership-manager' ); ?></h2>
        <p><?php _e( 'Manually trigger the automatic renewal process for a specific membership to test WooCommerce order creation and payment processing.', 'membership-manager' ); ?></p>
        
        <?php if ( ! empty( $active_subscriptions ) ) : ?>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top: 20px;">
                <input type="hidden" name="action" value="test_automatic_renewal">
                <?php wp_nonce_field( 'test_automatic_renewal_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="subscription_id"><?php _e( 'Select Membership', 'membership-manager' ); ?></label>
                        </th>
                        <td>
                            <select id="subscription_id" name="subscription_id" class="regular-text" required>
                                <option value=""><?php _e( '-- Select a membership --', 'membership-manager' ); ?></option>
                                <?php foreach ( $active_subscriptions as $subscription ) : 
                                    $user = get_user_by( 'ID', $subscription->user_id );
                                    $user_display = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'User ID: ' . $subscription->user_id;
                                    $days_until_expiry = '';
                                    if ( ! empty( $subscription->end_date ) && $subscription->end_date !== '0000-00-00 00:00:00' ) {
                                        $end_date = new DateTime( $subscription->end_date );
                                        $now = new DateTime();
                                        $diff = $now->diff( $end_date );
                                        $days_until_expiry = ' - ' . sprintf( __( '%d days until expiry', 'membership-manager' ), $diff->days );
                                    }
                                ?>
                                    <option value="<?php echo esc_attr( $subscription->id ); ?>">
                                        <?php echo esc_html( $user_display . ' - ' . ucfirst( $subscription->renewal_type ) . $days_until_expiry ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Select a membership to test automatic renewal order creation.', 'membership-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label>
                                <input type="checkbox" name="force_renewal" value="yes">
                                <?php _e( 'Force Renewal', 'membership-manager' ); ?>
                            </label>
                        </th>
                        <td>
                            <p class="description"><?php _e( 'Check this to force creation of a renewal order even if the membership is not near expiration. This is useful for testing without waiting for the actual expiration date.', 'membership-manager' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Test Automatic Renewal', 'membership-manager' ), 'primary', 'submit', false ); ?>
            </form>
        <?php else : ?>
            <div class="notice notice-warning inline">
                <p><?php _e( 'No active memberships found. Create at least one active membership to test automatic renewal.', 'membership-manager' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <hr style="margin: 40px 0;">

    <!-- Manual Renewal Process Test Section -->
    <div class="membership-test-section">
        <h2><?php _e( 'Run Full Renewal Process', 'membership-manager' ); ?></h2>
        <p><?php _e( 'Manually trigger the complete renewal cron job to process all memberships and send due reminder emails.', 'membership-manager' ); ?></p>
        
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top: 20px;" onsubmit="return confirm('<?php echo esc_js( __( 'This will process all active memberships and send reminder emails where applicable. Continue?', 'membership-manager' ) ); ?>');">
            <input type="hidden" name="action" value="run_renewal_process">
            <?php wp_nonce_field( 'run_renewal_process_nonce' ); ?>
            
            <p class="description">
                <?php _e( 'This will execute the same process that runs daily via cron:', 'membership-manager' ); ?>
            </p>
            <ul style="margin-left: 20px;">
                <li><?php _e( 'Check all active memberships for upcoming expirations', 'membership-manager' ); ?></li>
                <li><?php _e( 'Send reminder emails for memberships expiring in 30, 14, 7, or 1 day(s)', 'membership-manager' ); ?></li>
                <li><?php _e( 'Create automatic renewal orders for expiring automatic memberships', 'membership-manager' ); ?></li>
                <li><?php _e( 'Mark expired memberships as expired', 'membership-manager' ); ?></li>
            </ul>
            
            <?php submit_button( __( 'Run Renewal Process Now', 'membership-manager' ), 'secondary', 'submit', false ); ?>
        </form>
    </div>

    <hr style="margin: 40px 0;">

    <!-- View Recent Logs Section -->
    <div class="membership-test-section">
        <h2><?php _e( 'Recent Activity Log', 'membership-manager' ); ?></h2>
        <p><?php _e( 'View the most recent log entries to verify test results and troubleshoot issues.', 'membership-manager' ); ?></p>
        
        <div style="margin-top: 20px;">
            <a href="<?php echo admin_url( 'admin.php?page=membership-test-tools&view_logs=1' ); ?>" class="button button-secondary">
                <?php _e( 'View Logs', 'membership-manager' ); ?>
            </a>
        </div>
        
        <?php if ( isset( $_GET['view_logs'] ) ) : 
            $log_file = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'logs/membership.log';
            if ( file_exists( $log_file ) ) :
                $log_contents = file_get_contents( $log_file );
                $log_lines = explode( "\n", $log_contents );
                $recent_logs = array_slice( array_reverse( $log_lines ), 0, 50 );
                $recent_logs = array_reverse( $recent_logs );
        ?>
            <div style="margin-top: 20px; background: #f5f5f5; padding: 15px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                <?php echo esc_html( implode( "\n", $recent_logs ) ); ?>
            </div>
        <?php 
            else : 
        ?>
            <div class="notice notice-info inline" style="margin-top: 20px;">
                <p><?php _e( 'No log file found yet.', 'membership-manager' ); ?></p>
            </div>
        <?php 
            endif;
        endif; 
        ?>
    </div>
</div>

<style>
.membership-test-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-top: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.membership-test-section h2 {
    margin-top: 0;
    padding-top: 0;
}
</style>
