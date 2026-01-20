<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Renewals {

    private $emails;

    public function __construct() {
        $this->emails = new Membership_Emails();
    }

    /**
     * Create a WooCommerce renewal order for automatic renewal subscriptions
     * 
     * @param object $subscription The membership subscription object
     * @return int|false Order ID if successful, false on failure
     */
    public function create_renewal_order( $subscription ) {
        // Check for staging mode
        if ( defined( 'MEMBERSHIP_STAGING_MODE' ) && MEMBERSHIP_STAGING_MODE ) {
            Membership_Manager::log( 
                sprintf( 
                    __( '[STAGING MODE] Renewal blocked for subscription ID: %d (User: %d)', 'membership-manager' ), 
                    $subscription->id, 
                    $subscription->user_id 
                ), 
                'INFO' 
            );
            return false;
        }
        
        Membership_Manager::log( sprintf( __( 'Attempting to create renewal order for subscription ID: %d (User: %d)', 'membership-manager' ), $subscription->id, $subscription->user_id ) );
        
        // Get automatic renewal products from settings
        $automatic_products = get_option( 'membership_automatic_renewal_products', array() );
        
        if ( empty( $automatic_products ) ) {
            Membership_Manager::log( sprintf( __( 'No automatic renewal products configured. Cannot create renewal order for subscription ID: %d', 'membership-manager' ), $subscription->id ), 'ERROR' );
            return false;
        }
        
        // Use the first product from the list
        $product_id = $automatic_products[0];
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            Membership_Manager::log( sprintf( __( 'Product ID %d not found. Cannot create renewal order for subscription ID: %d', 'membership-manager' ), $product_id, $subscription->id ), 'ERROR' );
            return false;
        }
        
        try {
            // Create a new order
            $order = wc_create_order( array(
                'customer_id' => $subscription->user_id,
                'status' => 'pending',
            ) );
            
            if ( is_wp_error( $order ) ) {
                Membership_Manager::log( sprintf( __( 'Failed to create order: %s', 'membership-manager' ), $order->get_error_message() ), 'ERROR' );
                return false;
            }
            
            // Add product to order
            $order->add_product( $product, 1 );
            
            // Add order note
            $order->add_order_note( sprintf( __( 'Automatic renewal order for membership subscription ID: %d', 'membership-manager' ), $subscription->id ) );
            
            // Add custom meta to link order to subscription
            $order->update_meta_data( '_membership_subscription_id', $subscription->id );
            $order->update_meta_data( '_is_membership_renewal', 'yes' );
            
            // Calculate totals
            $order->calculate_totals();
            
            // Save order
            $order->save();
            
            Membership_Manager::log( sprintf( __( 'Created renewal order #%d for subscription ID: %d', 'membership-manager' ), $order->get_id(), $subscription->id ) );
            
            // Try to process payment automatically if payment method is available
            $this->process_automatic_payment( $order, $subscription );
            
            return $order->get_id();
            
        } catch ( Exception $e ) {
            Membership_Manager::log( sprintf( __( 'Exception creating renewal order: %s', 'membership-manager' ), $e->getMessage() ), 'ERROR' );
            return false;
        }
    }
    
    /**
     * Attempt to process automatic payment for renewal order
     * 
     * @param WC_Order $order The order to process
     * @param object $subscription The subscription object
     */
    private function process_automatic_payment( $order, $subscription ) {
        // Check if customer has a saved payment method (for gateways that support it)
        $payment_tokens = WC_Payment_Tokens::get_customer_tokens( $subscription->user_id );
        
        if ( ! empty( $payment_tokens ) ) {
            // Get the default payment token
            $default_token = null;
            foreach ( $payment_tokens as $token ) {
                if ( $token->is_default() ) {
                    $default_token = $token;
                    break;
                }
            }
            
            if ( ! $default_token && ! empty( $payment_tokens ) ) {
                // Use first available token if no default
                $default_token = reset( $payment_tokens );
            }
            
            if ( $default_token ) {
                // Set payment method on order
                $order->set_payment_method( $default_token->get_gateway_id() );
                $order->add_payment_token( $default_token );
                $order->save();
                
                Membership_Manager::log( sprintf( __( 'Payment method set for order #%d, attempting automatic payment', 'membership-manager' ), $order->get_id() ) );
                
                // Trigger payment processing
                // Note: This will need the gateway to support automatic charges
                do_action( 'membership_manager_process_renewal_payment', $order, $subscription );
                
                // Some gateways auto-process, try to complete if it was successful
                if ( $order->needs_payment() ) {
                    // Mark as pending payment
                    $order->update_status( 'pending', __( 'Awaiting automatic payment processing.', 'membership-manager' ) );
                    
                    // Send email to customer about pending payment
                    $this->send_payment_required_email( $order, $subscription );
                }
            } else {
                Membership_Manager::log( sprintf( __( 'No payment token found for user %d. Manual payment required for order #%d', 'membership-manager' ), $subscription->user_id, $order->get_id() ), 'WARNING' );
                $this->handle_failed_automatic_renewal( $order, $subscription, 'no_payment_method' );
            }
        } else {
            Membership_Manager::log( sprintf( __( 'No saved payment methods for user %d. Manual payment required for order #%d', 'membership-manager' ), $subscription->user_id, $order->get_id() ), 'WARNING' );
            $this->handle_failed_automatic_renewal( $order, $subscription, 'no_payment_method' );
        }
    }
    
    /**
     * Handle failed automatic renewal attempts
     * 
     * @param WC_Order $order The failed order
     * @param object $subscription The subscription object
     * @param string $reason The reason for failure
     */
    private function handle_failed_automatic_renewal( $order, $subscription, $reason = 'unknown' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Update subscription status to pending-cancel
        $wpdb->update(
            $table_name,
            array( 'status' => 'pending-cancel' ),
            array( 'id' => $subscription->id )
        );
        
        Membership_Manager::log( sprintf( __( 'Failed automatic renewal for subscription ID: %d. Reason: %s. Status set to pending-cancel.', 'membership-manager' ), $subscription->id, $reason ), 'ERROR' );
        
        // Send email to user about failed renewal
        $this->send_failed_renewal_email( $subscription, $order, $reason );
        
        // Notify admin
        $this->notify_admin_failed_renewal( $subscription, $order, $reason );
        
        // Hook for custom actions
        do_action( 'membership_manager_failed_renewal', $subscription, $order, $reason );
    }
    
    /**
     * Send email notification for payment required
     */
    private function send_payment_required_email( $order, $subscription ) {
        $user_info = get_userdata( $subscription->user_id );
        $to = $user_info->user_email;
        $subject = __( 'Payment Required for Membership Renewal', 'membership-manager' );
        
        $message = sprintf(
            __( 'Hi %s,<br><br>Your membership renewal order has been created but requires payment.<br><br>Please complete the payment here: %s<br><br>Order Details:<br>Order #%d<br>Amount: %s<br><br>Thank you!', 'membership-manager' ),
            $user_info->display_name,
            $order->get_checkout_payment_url(),
            $order->get_id(),
            $order->get_formatted_order_total()
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $to, $subject, $message, $headers );
        
        Membership_Manager::log( sprintf( __( 'Sent payment required email to: %s for order #%d', 'membership-manager' ), $to, $order->get_id() ) );
    }
    
    /**
     * Send email notification for failed renewal
     */
    private function send_failed_renewal_email( $subscription, $order, $reason ) {
        $user_info = get_userdata( $subscription->user_id );
        $to = $user_info->user_email;
        $subject = __( 'Action Required: Membership Renewal Failed', 'membership-manager' );
        
        $message = sprintf(
            __( 'Hi %s,<br><br>We were unable to automatically renew your membership.<br><br>Please update your payment method and complete the renewal here: %s<br><br>If you have any questions, please contact us.<br><br>Thank you!', 'membership-manager' ),
            $user_info->display_name,
            $order ? $order->get_checkout_payment_url() : wc_get_account_endpoint_url( 'membership' )
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $to, $subject, $message, $headers );
        
        Membership_Manager::log( sprintf( __( 'Sent failed renewal email to: %s', 'membership-manager' ), $to ) );
    }
    
    /**
     * Notify admin about failed renewal
     */
    private function notify_admin_failed_renewal( $subscription, $order, $reason ) {
        $admin_email = get_option( 'admin_email' );
        $subject = __( 'Failed Membership Renewal - Admin Notification', 'membership-manager' );
        
        $user_info = get_userdata( $subscription->user_id );
        $message = sprintf(
            __( 'A membership renewal has failed.<br><br>Subscription ID: %d<br>User: %s (ID: %d)<br>Email: %s<br>Order ID: %s<br>Reason: %s<br><br>Please take appropriate action.', 'membership-manager' ),
            $subscription->id,
            $user_info->display_name,
            $subscription->user_id,
            $user_info->user_email,
            $order ? '#' . $order->get_id() : 'N/A',
            $reason
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $admin_email, $subject, $message, $headers );
    }

    public function process_membership_renewals() {
        $this->process_expirations();

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        $subscriptions = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'active'" );

        Membership_Manager::log( 'Found ' . count( $subscriptions ) . ' active subscriptions to process.' );

        foreach ( $subscriptions as $subscription ) {
            $end_date = new DateTime( $subscription->end_date );
            $today = new DateTime();
            $today->setTime( 0, 0, 0 ); // Reset time to midnight for accurate comparison
            $end_date->setTime( 0, 0, 0 );
            
            $interval = $today->diff( $end_date );
            $days_left = (int) $interval->days;
            $is_future = $today < $end_date;
            
            // Skip if date is in the past (will be handled by expiration process)
            if ( ! $is_future && $days_left !== 0 ) {
                continue; 
            }

            $renewal_type = $subscription->renewal_type;
            
            // Handle automatic renewal on the expiration date (day 0)
            if ( $days_left === 0 && $renewal_type === 'automatic' ) {
                Membership_Manager::log( sprintf( __( 'Processing automatic renewal for subscription ID: %d on expiration date', 'membership-manager' ), $subscription->id ) );
                
                // Check if we already created an order today to avoid duplicates
                $existing_order = $wpdb->get_var( $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_membership_subscription_id' 
                    AND meta_value = %d 
                    AND post_id IN (
                        SELECT ID FROM {$wpdb->posts} 
                        WHERE post_type = 'shop_order' 
                        AND DATE(post_date) = CURDATE()
                    )",
                    $subscription->id
                ));
                
                if ( ! $existing_order ) {
                    $order_id = $this->create_renewal_order( $subscription );
                    
                    if ( $order_id ) {
                        Membership_Manager::log( sprintf( __( 'Successfully created automatic renewal order #%d for subscription ID: %d', 'membership-manager' ), $order_id, $subscription->id ) );
                    } else {
                        Membership_Manager::log( sprintf( __( 'Failed to create automatic renewal order for subscription ID: %d', 'membership-manager' ), $subscription->id ), 'ERROR' );
                    }
                } else {
                    Membership_Manager::log( sprintf( __( 'Renewal order already exists for subscription ID: %d today (Order #%d)', 'membership-manager' ), $subscription->id, $existing_order ) );
                }
            }

            // Send reminder emails
            $reminder_type = '';
            if ( $days_left == 30 ) {
                $reminder_type = '30_days';
            } elseif ( $days_left == 14 ) {
                $reminder_type = '14_days';
            } elseif ( $days_left == 7 ) {
                $reminder_type = '7_days';
            } elseif ( $days_left == 1 ) {
                $reminder_type = '1_day';
            }

            if ( ! empty( $reminder_type ) ) {
                Membership_Manager::log( "Sending {$reminder_type} reminder for subscription ID: {$subscription->id}" );
                if ( $renewal_type === 'automatic' ) {
                    $this->emails->send_automatic_renewal_reminders( $subscription, $reminder_type );
                } else {
                    $this->emails->send_manual_renewal_reminders( $subscription, $reminder_type );
                }
            }
        }
    }

    public function process_expirations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        $today = current_time( 'mysql' );

        $expired_subscriptions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_name WHERE status = 'active' AND end_date < %s AND end_date != '0000-00-00 00:00:00'", 
            $today 
        ) );

        if ( ! empty( $expired_subscriptions ) ) {
            Membership_Manager::log( 'Found ' . count( $expired_subscriptions ) . ' expired subscriptions. Updating status.' );
            
            foreach ( $expired_subscriptions as $subscription ) {
                $wpdb->update(
                    $table_name,
                    array( 'status' => 'expired' ),
                    array( 'id' => $subscription->id )
                );
                Membership_Manager::log( sprintf( 'Marked subscription ID %d (User %d) as expired.', $subscription->id, $subscription->user_id ) );
                
                // Hook for other actions (e.g. remove role)
                do_action( 'membership_manager_subscription_expired', $subscription->user_id, $subscription->id );
            }
        }
    }
}
