<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Checkout {

    public static function init() {
        // Hook into order completed
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'create_membership_on_order_complete' ) );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'create_membership_on_order_complete' ) );
        
        // Display membership info in order confirmation
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'display_membership_confirmation' ), 20 );
        
        // Add membership info to order emails
        add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_membership_to_email' ), 10, 4 );
    }

    /**
     * Create membership when order is completed
     */
    public static function create_membership_on_order_complete( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }
        
        // Check if memberships already created for this order
        $membership_created = get_post_meta( $order_id, '_membership_created', true );
        if ( $membership_created ) {
            return;
        }
        
        $user_id = $order->get_user_id();
        
        if ( ! $user_id ) {
            Membership_Manager::log( 'Cannot create membership: Order #' . $order_id . ' has no user', 'warning' );
            return;
        }
        
        $memberships_created = array();
        
        foreach ( $order->get_items() as $item ) {
            /** @var WC_Order_Item_Product $item */
            $product = $item->get_product();
            
            if ( ! $product ) {
                continue;
            }
            
            $product_type = $product->get_type();
            
            // Only process membership products
            if ( $product_type !== 'membership_auto' && $product_type !== 'membership_manual' ) {
                continue;
            }
            
            // Determine renewal type
            $renewal_type = ( $product_type === 'membership_auto' ) ? 'automatic' : 'manual';
            
            // Check if user already has an active membership
            $existing_membership = Membership_Manager::get_user_membership( $user_id );
            
            if ( $existing_membership && $existing_membership->status === 'active' ) {
                // Extend existing membership by 1 year
                $current_end_date = new DateTime( $existing_membership->end_date );
                $new_end_date = $current_end_date->modify( '+1 year' )->format( 'Y-m-d H:i:s' );
                
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'membership_subscriptions',
                    array(
                        'end_date' => $new_end_date,
                        'renewal_type' => $renewal_type,
                    ),
                    array( 'id' => $existing_membership->id )
                );
                
                Membership_Manager::log( 'Extended membership #' . $existing_membership->id . ' for user #' . $user_id . ' to ' . $new_end_date );
                
                $memberships_created[] = $existing_membership->id;
            } else {
                // Create new membership
                $start_date = current_time( 'mysql' );
                $end_date = date( 'Y-m-d H:i:s', strtotime( '+1 year', strtotime( $start_date ) ) );
                
                $membership_id = Membership_Manager::create_membership_subscription( 
                    $user_id, 
                    $start_date, 
                    $end_date, 
                    $renewal_type 
                );
                
                if ( $membership_id ) {
                    Membership_Manager::log( 'Created membership #' . $membership_id . ' for user #' . $user_id . ' (Order #' . $order_id . ')' );
                    $memberships_created[] = $membership_id;
                    
                    // Send welcome email
                    $user = get_userdata( $user_id );
                    $membership = Membership_Manager::get_membership( $membership_id );
                    
                    Membership_Emails::send_welcome_email( $user, $membership );
                } else {
                    Membership_Manager::log( 'Failed to create membership for user #' . $user_id . ' (Order #' . $order_id . ')', 'error' );
                }
            }
        }
        
        // Mark that memberships have been created for this order
        if ( ! empty( $memberships_created ) ) {
            update_post_meta( $order_id, '_membership_created', 'yes' );
            update_post_meta( $order_id, '_membership_ids', $memberships_created );
        }
    }

    /**
     * Display membership confirmation on thank you page
     */
    public static function display_membership_confirmation( $order_id ) {
        $membership_ids = get_post_meta( $order_id, '_membership_ids', true );
        
        if ( empty( $membership_ids ) ) {
            return;
        }
        
        ?>
        <section class="woocommerce-membership-confirmation">
            <h2><?php _e( 'Your Membership', 'membership-manager' ); ?></h2>
            
            <?php foreach ( (array) $membership_ids as $membership_id ): 
                $membership = Membership_Manager::get_membership( $membership_id );
                if ( ! $membership ) continue;
            ?>
            
            <div class="membership-details" style="background: #f7f7f7; padding: 20px; border-radius: 4px; margin: 15px 0;">
                <p>
                    <strong><?php _e( 'Status:', 'membership-manager' ); ?></strong>
                    <span class="membership-status membership-status-<?php echo esc_attr( $membership->status ); ?>">
                        <?php echo esc_html( ucfirst( $membership->status ) ); ?>
                    </span>
                </p>
                
                <p>
                    <strong><?php _e( 'Start Date:', 'membership-manager' ); ?></strong>
                    <?php echo date_i18n( get_option( 'date_format' ), strtotime( $membership->start_date ) ); ?>
                </p>
                
                <p>
                    <strong><?php _e( 'Expiry Date:', 'membership-manager' ); ?></strong>
                    <?php echo date_i18n( get_option( 'date_format' ), strtotime( $membership->end_date ) ); ?>
                </p>
                
                <p>
                    <strong><?php _e( 'Renewal Type:', 'membership-manager' ); ?></strong>
                    <?php 
                    if ( $membership->renewal_type === 'automatic' ) {
                        _e( 'Automatic - Will renew automatically', 'membership-manager' );
                    } else {
                        _e( 'Manual - You will receive renewal reminders', 'membership-manager' );
                    }
                    ?>
                </p>
                
                <?php if ( $membership->renewal_type === 'manual' && $membership->renewal_token ): ?>
                <p>
                    <a href="<?php echo esc_url( Membership_Manager::get_renewal_link( $membership->renewal_token ) ); ?>" class="button">
                        <?php _e( 'Renew Membership', 'membership-manager' ); ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
            
            <?php endforeach; ?>
        </section>
        <?php
    }

    /**
     * Add membership info to order emails
     */
    public static function add_membership_to_email( $order, $sent_to_admin, $plain_text, $email ) {
        // Only show in customer emails
        if ( $sent_to_admin ) {
            return;
        }
        
        $order_id = $order->get_id();
        $membership_ids = get_post_meta( $order_id, '_membership_ids', true );
        
        if ( empty( $membership_ids ) ) {
            return;
        }
        
        if ( $plain_text ) {
            echo "\n" . __( 'YOUR MEMBERSHIP', 'membership-manager' ) . "\n\n";
            
            foreach ( (array) $membership_ids as $membership_id ) {
                $membership = Membership_Manager::get_membership( $membership_id );
                if ( ! $membership ) continue;
                
                echo __( 'Status:', 'membership-manager' ) . ' ' . ucfirst( $membership->status ) . "\n";
                echo __( 'Start Date:', 'membership-manager' ) . ' ' . date_i18n( get_option( 'date_format' ), strtotime( $membership->start_date ) ) . "\n";
                echo __( 'Expiry Date:', 'membership-manager' ) . ' ' . date_i18n( get_option( 'date_format' ), strtotime( $membership->end_date ) ) . "\n";
                echo __( 'Renewal Type:', 'membership-manager' ) . ' ' . ucfirst( $membership->renewal_type ) . "\n";
                
                if ( $membership->renewal_type === 'manual' && $membership->renewal_token ) {
                    echo __( 'Renewal Link:', 'membership-manager' ) . ' ' . Membership_Manager::get_renewal_link( $membership->renewal_token ) . "\n";
                }
                
                echo "\n";
            }
        } else {
            ?>
            <h2><?php _e( 'Your Membership', 'membership-manager' ); ?></h2>
            
            <?php foreach ( (array) $membership_ids as $membership_id ): 
                $membership = Membership_Manager::get_membership( $membership_id );
                if ( ! $membership ) continue;
            ?>
            
            <table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
                <tr>
                    <th style="text-align:left; border: 1px solid #eee;"><?php _e( 'Status', 'membership-manager' ); ?></th>
                    <td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( ucfirst( $membership->status ) ); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left; border: 1px solid #eee;"><?php _e( 'Start Date', 'membership-manager' ); ?></th>
                    <td style="text-align:left; border: 1px solid #eee;"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $membership->start_date ) ); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left; border: 1px solid #eee;"><?php _e( 'Expiry Date', 'membership-manager' ); ?></th>
                    <td style="text-align:left; border: 1px solid #eee;"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $membership->end_date ) ); ?></td>
                </tr>
                <tr>
                    <th style="text-align:left; border: 1px solid #eee;"><?php _e( 'Renewal Type', 'membership-manager' ); ?></th>
                    <td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( ucfirst( $membership->renewal_type ) ); ?></td>
                </tr>
                <?php if ( $membership->renewal_type === 'manual' && $membership->renewal_token ): ?>
                <tr>
                    <th style="text-align:left; border: 1px solid #eee;"><?php _e( 'Renewal Link', 'membership-manager' ); ?></th>
                    <td style="text-align:left; border: 1px solid #eee;">
                        <a href="<?php echo esc_url( Membership_Manager::get_renewal_link( $membership->renewal_token ) ); ?>">
                            <?php _e( 'Click here to renew', 'membership-manager' ); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <br>
            
            <?php endforeach; ?>
            <?php
        }
    }
}
