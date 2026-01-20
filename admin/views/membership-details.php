<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$membership_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( ! $membership_id ) {
    wp_die( __( 'Ugyldigt medlemskabs-ID.', 'membership-manager' ) );
}

global $wpdb;
$table_name = $wpdb->prefix . 'membership_subscriptions';

$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $membership_id ) );

if ( ! $membership ) {
    wp_die( __( 'Medlemskab ikke fundet.', 'membership-manager' ) );
}

$user = get_user_by( 'ID', $membership->user_id );

if ( ! $user ) {
    wp_die( __( 'Bruger ikke fundet.', 'membership-manager' ) );
}

// Get user's orders if WooCommerce is active
$orders = array();
if ( class_exists( 'WooCommerce' ) ) {
    $orders = wc_get_orders( array(
        'customer' => $user->ID,
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ) );
}

// Get user meta
$user_meta = get_user_meta( $user->ID );
?>

<div class="wrap">
    <h1>
        <?php _e( 'Medlemskabsdetaljer', 'membership-manager' ); ?>
        <a href="<?php echo admin_url( 'admin.php?page=membership-manager' ); ?>" class="page-title-action"><?php _e( 'Tilbage til liste', 'membership-manager' ); ?></a>
    </h1>
    
    <?php
    // Show success messages
    if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Medlemskab opdateret!', 'membership-manager' ) . '</p></div>';
    }
    if ( isset( $_GET['paused'] ) && $_GET['paused'] === 'true' ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Medlemskab pauseret!', 'membership-manager' ) . '</p></div>';
    }
    if ( isset( $_GET['resumed'] ) && $_GET['resumed'] === 'true' ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Medlemskab genoptaget!', 'membership-manager' ) . '</p></div>';
    }
    if ( isset( $_GET['created'] ) && $_GET['created'] === 'true' ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Medlemskab oprettet!', 'membership-manager' ) . '</p></div>';
    }
    ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        
        <!-- Membership Information -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e( 'Medlemskabsinformation', 'membership-manager' ); ?></h2>
            </div>
            <div class="inside">
                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                    <input type="hidden" name="action" value="update_membership_details">
                    <input type="hidden" name="membership_id" value="<?php echo esc_attr( $membership->id ); ?>">
                    <?php wp_nonce_field( 'update_membership_details_nonce', '_wpnonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e( 'Medlemskabs-ID', 'membership-manager' ); ?></th>
                            <td><?php echo esc_html( $membership->id ); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="start_date"><?php _e( 'Startdato', 'membership-manager' ); ?></label></th>
                            <td>
                                <input type="datetime-local" id="start_date" name="start_date" value="<?php echo esc_attr( date( 'Y-m-d\TH:i', strtotime( $membership->start_date ) ) ); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="end_date"><?php _e( 'Slutdato', 'membership-manager' ); ?></label></th>
                            <td>
                                <input type="datetime-local" id="end_date" name="end_date" value="<?php echo esc_attr( date( 'Y-m-d\TH:i', strtotime( $membership->end_date ) ) ); ?>" class="regular-text">
                                <p class="description"><?php _e( 'Lad stå tomt for ingen udløbsdato.', 'membership-manager' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="status"><?php _e( 'Status', 'membership-manager' ); ?></label></th>
                            <td>
                                <select id="status" name="status">
                                    <?php
                                    $statuses = array( 'active', 'expired', 'pending-cancel', 'cancelled', 'on-hold' );
                                    foreach ( $statuses as $status ) {
                                        echo '<option value="' . esc_attr( $status ) . '" ' . selected( $membership->status, $status, false ) . '>' . esc_html( Membership_Manager::get_status_display_name( $status ) ) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="renewal_type"><?php _e( 'Fornyelsestype', 'membership-manager' ); ?></label></th>
                            <td>
                                <select id="renewal_type" name="renewal_type">
                                    <option value="manual" <?php selected( $membership->renewal_type, 'manual' ); ?>><?php _e( 'Manuel', 'membership-manager' ); ?></option>
                                    <option value="automatic" <?php selected( $membership->renewal_type, 'automatic' ); ?>><?php _e( 'Automatisk', 'membership-manager' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <?php if ( ! empty( $membership->status_changed_date ) ): ?>
                        <tr>
                            <th scope="row"><?php _e( 'Status ændret', 'membership-manager' ); ?></th>
                            <td><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $membership->status_changed_date ) ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( $membership->status === 'on-hold' && ! empty( $membership->paused_date ) ): ?>
                        <tr>
                            <th scope="row"><?php _e( 'Pausedato', 'membership-manager' ); ?></th>
                            <td>
                                <strong style="color: #826eb4;"><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $membership->paused_date ) ); ?></strong>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="button button-primary"><?php _e( 'Opdater medlemskab', 'membership-manager' ); ?></button>
                            
                            <?php if ( $membership->status === 'active' ): ?>
                                <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=pause_membership&membership_id=' . $membership->id ), 'pause_membership_nonce' ); ?>" 
                                   class="button button-secondary"
                                   onclick="return confirm('<?php _e( 'Er du sikker på, at du vil pause dette medlemskab?', 'membership-manager' ); ?>');">
                                    <span class="dashicons dashicons-controls-pause" style="vertical-align: middle;"></span>
                                    <?php _e( 'Pause medlemskab', 'membership-manager' ); ?>
                                </a>
                            <?php elseif ( $membership->status === 'on-hold' ): ?>
                                <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=resume_membership&membership_id=' . $membership->id ), 'resume_membership_nonce' ); ?>" 
                                   class="button button-secondary"
                                   onclick="return confirm('<?php _e( 'Er du sikker på, at du vil genoptage dette medlemskab?', 'membership-manager' ); ?>');">
                                    <span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span>
                                    <?php _e( 'Genoptag medlemskab', 'membership-manager' ); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ( $membership->renewal_type === 'manual' && ! empty( $membership->renewal_token ) ): ?>
                                <?php $renewal_link = Membership_Manager::get_renewal_link( $membership ); ?>
                                <button type="button" class="button button-secondary" onclick="copyToClipboard('<?php echo esc_js( $renewal_link ); ?>')">
                                    <span class="dashicons dashicons-admin-links" style="vertical-align: middle;"></span>
                                    <?php _e( 'Kopier fornyelseslink', 'membership-manager' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=delete_membership&membership_id=' . $membership->id ), 'delete_membership_nonce' ); ?>" 
                           class="button button-link-delete" 
                           onclick="return confirm('<?php _e( 'Er du sikker på, at du vil slette dette medlemskab? Dette kan ikke fortrydes.', 'membership-manager' ); ?>');">
                            <?php _e( 'Slet medlemskab', 'membership-manager' ); ?>
                        </a>
                    </div>
                    
                    <script>
                    function copyToClipboard(text) {
                        navigator.clipboard.writeText(text).then(function() {
                            alert('<?php _e( 'Fornyelseslink kopieret til udklipsholder!', 'membership-manager' ); ?>');
                        });
                    }
                    </script>
                </form>
            </div>
        </div>

        <!-- User Information -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e( 'Brugerinformation', 'membership-manager' ); ?></h2>
            </div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Bruger-ID', 'membership-manager' ); ?></th>
                        <td>
                            <a href="<?php echo admin_url( 'user-edit.php?user_id=' . $user->ID ); ?>" target="_blank">
                                <?php echo esc_html( $user->ID ); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Navn', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( $user->display_name ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'E-mail', 'membership-manager' ); ?></th>
                        <td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Brugernavn', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( $user->user_login ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Registreringsdato', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?></td>
                    </tr>
                    <?php if ( isset( $user_meta['first_name'][0] ) || isset( $user_meta['last_name'][0] ) ): ?>
                    <tr>
                        <th scope="row"><?php _e( 'Fulde navn', 'membership-manager' ); ?></th>
                        <td>
                            <?php 
                            $first_name = isset( $user_meta['first_name'][0] ) ? $user_meta['first_name'][0] : '';
                            $last_name = isset( $user_meta['last_name'][0] ) ? $user_meta['last_name'][0] : '';
                            echo esc_html( trim( $first_name . ' ' . $last_name ) );
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <?php if ( class_exists( 'WooCommerce' ) && ! empty( $orders ) ): ?>
    <!-- Orders History -->
    <div class="postbox" style="margin-top: 20px;">
        <div class="postbox-header">
            <h2 class="hndle"><?php _e( 'Ordrehistorik', 'membership-manager' ); ?></h2>
        </div>
        <div class="inside">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Ordre #', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Dato', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Status', 'membership-manager' ); ?></th>
                        <th><?php _e( 'I alt', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Betalingsmetode', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Handlinger', 'membership-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( array_slice( $orders, 0, 10 ) as $order ): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ); ?>" target="_blank">
                                #<?php echo $order->get_order_number(); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ); ?></td>
                        <td>
                            <span class="order-status status-<?php echo esc_attr( $order->get_status() ); ?>">
                                <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                            </span>
                        </td>
                        <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                        <td><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
                        <td>
                            <a href="<?php echo admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ); ?>" target="_blank" class="button button-small">
                                <?php _e( 'Vis', 'membership-manager' ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( count( $orders ) > 10 ): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #646970; font-style: italic;">
                            <?php printf( __( 'Viser 10 af %d ordrer', 'membership-manager' ), count( $orders ) ); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( class_exists( 'WooCommerce' ) ): ?>
    <!-- Billing Information -->
    <div class="postbox" style="margin-top: 20px;">
        <div class="postbox-header">
            <h2 class="hndle"><?php _e( 'Faktureringsinformation', 'membership-manager' ); ?></h2>
        </div>
        <div class="inside">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4><?php _e( 'Faktureringsadresse', 'membership-manager' ); ?></h4>
                    <?php
                    $billing_address = array(
                        $user_meta['billing_first_name'][0] ?? '',
                        $user_meta['billing_last_name'][0] ?? '',
                        $user_meta['billing_address_1'][0] ?? '',
                        $user_meta['billing_address_2'][0] ?? '',
                        $user_meta['billing_city'][0] ?? '',
                        $user_meta['billing_postcode'][0] ?? '',
                        $user_meta['billing_country'][0] ?? ''
                    );
                    $billing_address = array_filter( $billing_address );
                    
                    if ( ! empty( $billing_address ) ):
                    ?>
                        <p><?php echo esc_html( implode( ', ', $billing_address ) ); ?></p>
                    <?php else: ?>
                        <p><em><?php _e( 'No billing address on file', 'membership-manager' ); ?></em></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h4><?php _e( 'Contact Information', 'membership-manager' ); ?></h4>
                    <?php if ( isset( $user_meta['billing_phone'][0] ) && ! empty( $user_meta['billing_phone'][0] ) ): ?>
                        <p><strong><?php _e( 'Phone:', 'membership-manager' ); ?></strong> 
                        <a href="tel:<?php echo esc_attr( $user_meta['billing_phone'][0] ); ?>"><?php echo esc_html( $user_meta['billing_phone'][0] ); ?></a></p>
                    <?php endif; ?>
                    
                    <?php if ( isset( $user_meta['billing_email'][0] ) && ! empty( $user_meta['billing_email'][0] ) ): ?>
                        <p><strong><?php _e( 'Billing Email:', 'membership-manager' ); ?></strong> 
                        <a href="mailto:<?php echo esc_attr( $user_meta['billing_email'][0] ); ?>"><?php echo esc_html( $user_meta['billing_email'][0] ); ?></a></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.status-active { background-color: #00a32a !important; }
.status-expired { background-color: #d63638 !important; }
.status-pending-cancel { background-color: #f0b849 !important; }
.status-cancelled { background-color: #646970 !important; }
.status-on-hold { background-color: #72aee6 !important; }

.order-status {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.order-status.status-completed { background-color: #00a32a; }
.order-status.status-processing { background-color: #f0b849; }
.order-status.status-on-hold { background-color: #72aee6; }
.order-status.status-pending { background-color: #646970; }
.order-status.status-cancelled { background-color: #d63638; }
.order-status.status-refunded { background-color: #646970; }
.order-status.status-failed { background-color: #d63638; }
</style>