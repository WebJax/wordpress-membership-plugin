<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get queue statistics
$stats = Membership_Email_Queue::get_stats();
$queue = get_option( 'membership_email_queue', array() );

// Sort queue by queued_at date (newest first)
usort( $queue, function( $a, $b ) {
    return $b['queued_at'] - $a['queued_at'];
});
?>

<div class="wrap">
    <h1><?php _e( 'E-mail Kø', 'membership-manager' ); ?></h1>
    
    <?php if ( defined( 'MEMBERSHIP_STAGING_MODE' ) && MEMBERSHIP_STAGING_MODE ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e( '⚠️ STAGING MODE AKTIV', 'membership-manager' ); ?></strong>
                <?php _e( 'E-mail køen vil ikke blive behandlet i staging mode.', 'membership-manager' ); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="membership-email-queue-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
        <div class="stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #1d2327; font-size: 14px; font-weight: 600;"><?php _e( 'I alt', 'membership-manager' ); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: 700; color: #2271b1;"><?php echo esc_html( $stats['total'] ); ?></p>
        </div>
        
        <div class="stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #1d2327; font-size: 14px; font-weight: 600;"><?php _e( 'Afventer', 'membership-manager' ); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: 700; color: #00a32a;"><?php echo esc_html( $stats['pending'] ); ?></p>
        </div>
        
        <div class="stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #f0b849; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #1d2327; font-size: 14px; font-weight: 600;"><?php _e( 'Prøver igen', 'membership-manager' ); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: 700; color: #f0b849;"><?php echo esc_html( $stats['retry'] ); ?></p>
        </div>
        
        <div class="stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #1d2327; font-size: 14px; font-weight: 600;"><?php _e( 'Fejlet', 'membership-manager' ); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: 700; color: #d63638;"><?php echo esc_html( $stats['failed'] ); ?></p>
        </div>
    </div>
    
    <div class="membership-queue-actions" style="margin: 20px 0; display: flex; gap: 10px;">
        <form method="post" style="display: inline;">
            <?php wp_nonce_field( 'membership_email_queue_action' ); ?>
            <input type="hidden" name="action" value="process_now" />
            <button type="submit" class="button button-primary">
                <?php _e( 'Behandl kø nu', 'membership-manager' ); ?>
            </button>
        </form>
        
        <?php if ( $stats['failed'] > 0 ) : ?>
        <form method="post" style="display: inline;">
            <?php wp_nonce_field( 'membership_email_queue_action' ); ?>
            <input type="hidden" name="action" value="retry_failed" />
            <button type="submit" class="button">
                <?php _e( 'Prøv fejlede igen', 'membership-manager' ); ?>
            </button>
        </form>
        <?php endif; ?>
        
        <?php if ( $stats['total'] > 0 ) : ?>
        <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Er du sikker på, at du vil rydde hele køen? Dette kan ikke fortrydes.', 'membership-manager' ); ?>');">
            <?php wp_nonce_field( 'membership_email_queue_action' ); ?>
            <input type="hidden" name="action" value="clear_queue" />
            <button type="submit" class="button button-link-delete">
                <?php _e( 'Ryd kø', 'membership-manager' ); ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <?php if ( ! empty( $queue ) ) : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 100px;"><?php _e( 'Status', 'membership-manager' ); ?></th>
                <th><?php _e( 'Til', 'membership-manager' ); ?></th>
                <th><?php _e( 'Emne', 'membership-manager' ); ?></th>
                <th><?php _e( 'Type', 'membership-manager' ); ?></th>
                <th><?php _e( 'Forsøg', 'membership-manager' ); ?></th>
                <th><?php _e( 'Tilføjet', 'membership-manager' ); ?></th>
                <th><?php _e( 'Sidste forsøg', 'membership-manager' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $queue as $email ) : 
                $status_colors = array(
                    'pending' => '#00a32a',
                    'retry' => '#f0b849',
                    'failed' => '#d63638',
                );
                $status_color = isset( $status_colors[ $email['status'] ] ) ? $status_colors[ $email['status'] ] : '#646970';
            ?>
            <tr>
                <td>
                    <span style="display: inline-block; padding: 4px 8px; background: <?php echo esc_attr( $status_color ); ?>; color: #fff; border-radius: 3px; font-size: 12px; font-weight: 600;">
                        <?php echo esc_html( ucfirst( $email['status'] ) ); ?>
                    </span>
                </td>
                <td><?php echo esc_html( $email['to'] ); ?></td>
                <td><?php echo esc_html( $email['subject'] ); ?></td>
                <td><code><?php echo esc_html( $email['type'] ); ?></code></td>
                <td><?php echo esc_html( $email['attempts'] ); ?> / <?php echo esc_html( Membership_Email_Queue::MAX_ATTEMPTS ); ?></td>
                <td>
                    <?php 
                    echo esc_html( 
                        human_time_diff( $email['queued_at'], current_time( 'timestamp' ) ) 
                    ) . ' ' . __( 'siden', 'membership-manager' ); 
                    ?>
                </td>
                <td>
                    <?php 
                    if ( $email['last_attempt'] > 0 ) {
                        echo esc_html( 
                            human_time_diff( $email['last_attempt'], current_time( 'timestamp' ) ) 
                        ) . ' ' . __( 'siden', 'membership-manager' );
                    } else {
                        echo '—';
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <div class="notice notice-info">
        <p><?php _e( 'E-mail køen er tom.', 'membership-manager' ); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="membership-queue-info" style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
        <h3><?php _e( 'Om E-mail Køen', 'membership-manager' ); ?></h3>
        <ul>
            <li><?php _e( 'E-mails behandles automatisk hver time via WordPress cron.', 'membership-manager' ); ?></li>
            <li><?php printf( __( 'Hver batch behandler op til %d e-mails ad gangen.', 'membership-manager' ), Membership_Email_Queue::BATCH_SIZE ); ?></li>
            <li><?php printf( __( 'Fejlede e-mails prøves op til %d gange med mindst 5 minutters mellemrum.', 'membership-manager' ), Membership_Email_Queue::MAX_ATTEMPTS ); ?></li>
            <li><?php printf( __( 'E-mails ældre end %d dage fjernes automatisk fra køen.', 'membership-manager' ), Membership_Email_Queue::MAX_AGE / 86400 ); ?></li>
        </ul>
    </div>
</div>
