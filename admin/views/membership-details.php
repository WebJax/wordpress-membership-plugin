<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$membership_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( ! $membership_id ) {
    wp_die( __( 'Invalid membership ID.', 'membership-manager' ) );
}

global $wpdb;
$table_name = $wpdb->prefix . 'membership_subscriptions';

$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $membership_id ) );

if ( ! $membership ) {
    wp_die( __( 'Membership not found.', 'membership-manager' ) );
}

$user = get_user_by( 'ID', $membership->user_id );

if ( ! $user ) {
    wp_die( __( 'User not found.', 'membership-manager' ) );
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
        <?php _e( 'Membership Details', 'membership-manager' ); ?>
        <a href="<?php echo admin_url( 'admin.php?page=membership-manager' ); ?>" class="page-title-action"><?php _e( 'Back to List', 'membership-manager' ); ?></a>
    </h1>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        
        <!-- Membership Information -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e( 'Membership Information', 'membership-manager' ); ?></h2>
            </div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Membership ID', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( $membership->id ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Start Date', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( Membership_Manager::format_date_safely( $membership->start_date ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'End Date', 'membership-manager' ); ?></th>
                        <td><?php echo Membership_Manager::format_end_date_with_status( $membership ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Status', 'membership-manager' ); ?></th>
                        <td>
                            <span class="status-<?php echo esc_attr( $membership->status ); ?>" style="padding: 4px 8px; border-radius: 3px; font-weight: 600; 
                                background: <?php echo $membership->status === 'active' ? '#00a32a' : ($membership->status === 'expired' ? '#d63638' : '#646970'); ?>; 
                                color: white; font-size: 12px;">
                                <?php echo esc_html( Membership_Manager::get_status_display_name( $membership->status ) ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Renewal Type', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( ucfirst( $membership->renewal_type ) ); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- User Information -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e( 'User Information', 'membership-manager' ); ?></h2>
            </div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'User ID', 'membership-manager' ); ?></th>
                        <td>
                            <a href="<?php echo admin_url( 'user-edit.php?user_id=' . $user->ID ); ?>" target="_blank">
                                <?php echo esc_html( $user->ID ); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Name', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( $user->display_name ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Email', 'membership-manager' ); ?></th>
                        <td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Username', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( $user->user_login ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Registration Date', 'membership-manager' ); ?></th>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?></td>
                    </tr>
                    <?php if ( isset( $user_meta['first_name'][0] ) || isset( $user_meta['last_name'][0] ) ): ?>
                    <tr>
                        <th scope="row"><?php _e( 'Full Name', 'membership-manager' ); ?></th>
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
            <h2 class="hndle"><?php _e( 'Order History', 'membership-manager' ); ?></h2>
        </div>
        <div class="inside">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Order #', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Date', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Status', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Total', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Payment Method', 'membership-manager' ); ?></th>
                        <th><?php _e( 'Actions', 'membership-manager' ); ?></th>
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
                                <?php _e( 'View', 'membership-manager' ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( count( $orders ) > 10 ): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #646970; font-style: italic;">
                            <?php printf( __( 'Showing 10 of %d orders', 'membership-manager' ), count( $orders ) ); ?>
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
            <h2 class="hndle"><?php _e( 'Billing Information', 'membership-manager' ); ?></h2>
        </div>
        <div class="inside">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4><?php _e( 'Billing Address', 'membership-manager' ); ?></h4>
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