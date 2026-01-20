<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Dashboard {

    public static function init() {
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ) );
    }

    /**
     * Add dashboard widgets
     */
    public static function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'membership_manager_status',
            __( 'Medlemskabsstatus', 'membership-manager' ),
            array( __CLASS__, 'render_status_widget' )
        );
        
        wp_add_dashboard_widget(
            'membership_manager_issues',
            __( 'Medlemskabsproblemer og advarsler', 'membership-manager' ),
            array( __CLASS__, 'render_issues_widget' )
        );
    }

    /**
     * Render membership status widget
     */
    public static function render_status_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Get counts by status
        $counts = Membership_Manager::get_membership_status_counts();
        
        // Get memberships expiring in the next 7 days
        $expiring_soon = $wpdb->get_results(
            "SELECT * FROM $table_name 
            WHERE status = 'active' 
            AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            ORDER BY end_date ASC
            LIMIT 5"
        );
        
        ?>
        <div class="membership-dashboard-widget">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px;">
                <div style="background: #00a32a; color: white; padding: 15px; border-radius: 4px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html( $counts['active'] ); ?></div>
                    <div style="opacity: 0.9;"><?php _e( 'Aktiv', 'membership-manager' ); ?></div>
                </div>
                <div style="background: #d63638; color: white; padding: 15px; border-radius: 4px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html( $counts['expired'] ); ?></div>
                    <div style="opacity: 0.9;"><?php _e( 'Udløbet', 'membership-manager' ); ?></div>
                </div>
                <div style="background: #dba617; color: white; padding: 15px; border-radius: 4px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html( $counts['pending-cancel'] ); ?></div>
                    <div style="opacity: 0.9;"><?php _e( 'Afventer annullering', 'membership-manager' ); ?></div>
                </div>
                <div style="background: #826eb4; color: white; padding: 15px; border-radius: 4px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html( $counts['on-hold'] ); ?></div>
                    <div style="opacity: 0.9;"><?php _e( 'På hold', 'membership-manager' ); ?></div>
                </div>
                <div style="background: #646970; color: white; padding: 15px; border-radius: 4px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold;"><?php echo esc_html( $counts['cancelled'] ); ?></div>
                    <div style="opacity: 0.9;"><?php _e( 'Annulleret', 'membership-manager' ); ?></div>
                </div>
            </div>
            
            <?php if ( ! empty( $expiring_soon ) ): ?>
                <h4 style="margin: 15px 0 10px 0;"><?php _e( 'Udløber denne uge', 'membership-manager' ); ?></h4>
                <table class="widefat" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th><?php _e( 'Bruger', 'membership-manager' ); ?></th>
                            <th><?php _e( 'Udløber', 'membership-manager' ); ?></th>
                            <th><?php _e( 'Type', 'membership-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $expiring_soon as $membership ): 
                            $user = get_user_by( 'ID', $membership->user_id );
                            $days_left = ceil( ( strtotime( $membership->end_date ) - time() ) / DAY_IN_SECONDS );
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership->id ); ?>">
                                    <?php echo $user ? esc_html( $user->display_name ) : 'User #' . $membership->user_id; ?>
                                </a>
                            </td>
                            <td style="color: <?php echo $days_left <= 1 ? '#d63638' : '#dba617'; ?>;">
                                <?php 
                                printf( 
                                    _n( 'In %d day', 'In %d days', $days_left, 'membership-manager' ), 
                                    $days_left 
                                ); 
                                ?>
                            </td>
                            <td><?php echo esc_html( ucfirst( $membership->renewal_type ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e( 'No memberships expiring in the next 7 days.', 'membership-manager' ); ?></p>
            <?php endif; ?>
            
            <p style="margin-top: 15px;">
                <a href="<?php echo admin_url( 'admin.php?page=membership-manager' ); ?>" class="button button-primary">
                    <?php _e( 'View All Memberships', 'membership-manager' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Render issues and alerts widget
     */
    public static function render_issues_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Get pending-cancel memberships (failed renewals)
        $failed_renewals = $wpdb->get_results(
            "SELECT * FROM $table_name 
            WHERE status = 'pending-cancel'
            ORDER BY end_date ASC
            LIMIT 10"
        );
        
        // Get memberships without tokens
        $missing_tokens = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name 
            WHERE (renewal_token IS NULL OR renewal_token = '') 
            AND renewal_type = 'manual'"
        );
        
        // Parse log file for recent errors
        $log_file = plugin_dir_path( __FILE__ ) . '../logs/membership.log';
        $recent_errors = array();
        
        if ( file_exists( $log_file ) ) {
            $lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            $lines = array_reverse( $lines ); // Get most recent first
            $error_count = 0;
            
            foreach ( $lines as $line ) {
                if ( strpos( $line, '[ERROR]' ) !== false && $error_count < 5 ) {
                    $recent_errors[] = $line;
                    $error_count++;
                }
            }
        }
        
        $has_issues = ! empty( $failed_renewals ) || $missing_tokens > 0 || ! empty( $recent_errors );
        
        ?>
        <div class="membership-issues-widget">
            <?php if ( ! $has_issues ): ?>
                <div style="text-align: center; padding: 20px;">
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 48px;"></span>
                    <p style="font-size: 16px; margin-top: 10px;"><?php _e( 'No issues detected!', 'membership-manager' ); ?></p>
                </div>
            <?php else: ?>
                
                <?php if ( ! empty( $failed_renewals ) ): ?>
                    <div style="padding: 10px; background: #fcf3cf; border-left: 4px solid #dba617; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0;">
                            <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                            <?php printf( _n( '%d Failed Renewal', '%d Failed Renewals', count( $failed_renewals ), 'membership-manager' ), count( $failed_renewals ) ); ?>
                        </h4>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ( array_slice( $failed_renewals, 0, 5 ) as $membership ): 
                                $user = get_user_by( 'ID', $membership->user_id );
                            ?>
                            <li style="margin: 5px 0;">
                                <a href="<?php echo admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership->id ); ?>">
                                    <?php echo $user ? esc_html( $user->display_name ) : 'User #' . $membership->user_id; ?>
                                </a>
                                - <small><?php _e( 'Requires attention', 'membership-manager' ); ?></small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ( $missing_tokens > 0 ): ?>
                    <div style="padding: 10px; background: #e8f4f8; border-left: 4px solid #2271b1; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0;">
                            <span class="dashicons dashicons-admin-links" style="color: #2271b1;"></span>
                            <?php printf( _n( '%d Membership Missing Token', '%d Memberships Missing Tokens', $missing_tokens, 'membership-manager' ), $missing_tokens ); ?>
                        </h4>
                        <p style="margin: 0;">
                            <?php _e( 'Some manual memberships are missing renewal tokens.', 'membership-manager' ); ?>
                            <a href="<?php echo admin_url( 'admin.php?page=membership-migration' ); ?>">
                                <?php _e( 'Generate tokens', 'membership-manager' ); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $recent_errors ) ): ?>
                    <div style="padding: 10px; background: #f8d7da; border-left: 4px solid #d63638; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0;">
                            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                            <?php _e( 'Recent Errors', 'membership-manager' ); ?>
                        </h4>
                        <div style="background: white; padding: 10px; font-size: 11px; font-family: monospace; max-height: 150px; overflow-y: auto;">
                            <?php foreach ( $recent_errors as $error ): ?>
                                <div style="margin: 5px 0; color: #d63638;"><?php echo esc_html( $error ); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <p style="margin-top: 15px;">
                <a href="<?php echo admin_url( 'admin.php?page=membership-settings' ); ?>" class="button">
                    <?php _e( 'Indstillinger', 'membership-manager' ); ?>
                </a>
                <a href="<?php echo esc_url( plugin_dir_url( __FILE__ ) . '../logs/membership.log' ); ?>" class="button" target="_blank">
                    <?php _e( 'View Full Log', 'membership-manager' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
