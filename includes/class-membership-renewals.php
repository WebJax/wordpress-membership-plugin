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
     * Uses database transactions to ensure data consistency
     * 
     * @param object $subscription The membership subscription object
     * @return int|false Order ID if successful, false on failure
     */
    public function create_renewal_order( $subscription ) {
        global $wpdb;
        
        // Check for staging mode
        if ( defined( 'MEMBERSHIP_STAGING_MODE' ) && MEMBERSHIP_STAGING_MODE ) {
            Membership_Manager::log( 
                sprintf( 
                    __( '[STAGING MODE] Fornyelse blokeret for abonnements-ID: %d (Bruger: %d)', 'membership-manager' ), 
                    $subscription->id, 
                    $subscription->user_id 
                ), 
                'INFO' 
            );
            return false;
        }
        
        Membership_Manager::log( sprintf( __( 'Forsøger at oprette fornyelsesordre for abonnements-ID: %d (Bruger: %d)', 'membership-manager' ), $subscription->id, $subscription->user_id ) );
        
        // Validate product availability before starting transaction
        $product = $this->get_renewal_product();
        if ( ! $product ) {
            return false;
        }
        
        // Start database transaction
        $wpdb->query( 'START TRANSACTION' );
        
        try {
            // Create the order
            $order = $this->create_wc_order( $subscription, $product );
            
            if ( ! $order ) {
                throw new Exception( __( 'Kunne ikke oprette WooCommerce ordre', 'membership-manager' ) );
            }
            
            Membership_Manager::log( sprintf( __( 'Oprettede fornyelsesordre #%d for abonnements-ID: %d', 'membership-manager' ), $order->get_id(), $subscription->id ) );
            
            // Commit transaction before payment processing
            $wpdb->query( 'COMMIT' );
            
            // Try to process payment automatically if payment method is available
            // This is done after commit since payment processing is external
            $this->process_automatic_payment( $order, $subscription );
            
            return $order->get_id();
            
        } catch ( Exception $e ) {
            // Rollback transaction on any error
            $wpdb->query( 'ROLLBACK' );
            Membership_Manager::log( sprintf( __( 'Undtagelse ved oprettelse af fornyelsesordre: %s', 'membership-manager' ), $e->getMessage() ), 'ERROR' );
            return false;
        }
    }
    
    /**
     * Get the renewal product for order creation
     * 
     * @return WC_Product|false Product object or false if not found
     */
    private function get_renewal_product() {
        $automatic_products = get_option( Membership_Constants::OPTION_AUTO_PRODUCTS, array() );
        
        if ( empty( $automatic_products ) ) {
            Membership_Manager::log( __( 'Ingen automatiske fornyelsesprodukter konfigureret.', 'membership-manager' ), 'ERROR' );
            return false;
        }
        
        // Use the first product from the list
        $product_id = $automatic_products[0];
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            Membership_Manager::log( sprintf( __( 'Produkt-ID %d ikke fundet.', 'membership-manager' ), $product_id ), 'ERROR' );
            return false;
        }
        
        return $product;
    }
    
    /**
     * Create a WooCommerce order for renewal
     * 
     * @param object $subscription The subscription object
     * @param WC_Product $product The product to add to the order
     * @return WC_Order|false Order object or false on failure
     */
    private function create_wc_order( $subscription, $product ) {
        $order = wc_create_order( array(
            'customer_id' => $subscription->user_id,
            'status' => 'pending',
        ) );
        
        if ( is_wp_error( $order ) ) {
            Membership_Manager::log( sprintf( __( 'Kunne ikke oprette ordre: %s', 'membership-manager' ), $order->get_error_message() ), 'ERROR' );
            return false;
        }
        
        // Add product to order
        $order->add_product( $product, 1 );
        
        // Add order note
        $order->add_order_note( sprintf( __( 'Automatisk fornyelsesordre for medlemskabsabonnement ID: %d', 'membership-manager' ), $subscription->id ) );
        
        // Add custom meta to link order to subscription
        $order->update_meta_data( Membership_Constants::ORDER_META_SUBSCRIPTION_ID, $subscription->id );
        $order->update_meta_data( Membership_Constants::ORDER_META_IS_RENEWAL, 'yes' );
        
        // Calculate totals
        $order->calculate_totals();
        
        // Save order
        $order->save();
        
        return $order;
    }
    
    /**
     * Attempt to process automatic payment for renewal order
     * 
     * @param WC_Order $order The order to process
     * @param object $subscription The subscription object
     */
    private function process_automatic_payment( $order, $subscription ) {
        // Get saved payment method
        $payment_token = $this->get_customer_payment_token( $subscription->user_id );
        
        if ( ! $payment_token ) {
            Membership_Manager::log( sprintf( __( 'Ingen gemte betalingsmetoder for bruger %d. Manuel betaling påkrævet for ordre #%d', 'membership-manager' ), $subscription->user_id, $order->get_id() ), 'WARNING' );
            $this->handle_failed_automatic_renewal( $order, $subscription, 'no_payment_method' );
            return;
        }
        
        // Set payment method on order
        $this->set_order_payment_method( $order, $payment_token );
        
        Membership_Manager::log( sprintf( __( 'Payment method set for order #%d, attempting automatic payment', 'membership-manager' ), $order->get_id() ) );
        
        // Allow payment gateways to process the renewal payment
        // Payment gateways should hook into this action to process the payment
        do_action( Membership_Constants::HOOK_PROCESS_RENEWAL_PAYMENT, $order, $subscription );
        
        // Allow third-party gateways to implement custom payment processing
        // Filter allows gateways to indicate if they've handled payment
        $payment_processed = apply_filters( 'membership_manager_renewal_payment_processed', false, $order, $subscription, $payment_token );
        
        // Check if payment still needs to be completed
        if ( $order->needs_payment() && ! $payment_processed ) {
            // Mark as pending payment
            $order->update_status( 'pending', __( 'Afventer automatisk betalingsbehandling.', 'membership-manager' ) );
            
            // Send email to customer about pending payment
            $this->send_payment_required_email( $order, $subscription );
        }
    }
    
    /**
     * Get the customer's preferred payment token
     * 
     * @param int $user_id The customer user ID
     * @return WC_Payment_Token|false Payment token or false if not found
     */
    private function get_customer_payment_token( $user_id ) {
        $payment_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id );
        
        if ( empty( $payment_tokens ) ) {
            return false;
        }
        
        // Try to find default token
        foreach ( $payment_tokens as $token ) {
            if ( $token->is_default() ) {
                return $token;
            }
        }
        
        // Use first available token if no default
        return reset( $payment_tokens );
    }
    
    /**
     * Set payment method on order
     * 
     * @param WC_Order $order The order
     * @param WC_Payment_Token $token The payment token
     */
    private function set_order_payment_method( $order, $token ) {
        $order->set_payment_method( $token->get_gateway_id() );
        $order->add_payment_token( $token );
        $order->save();
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
        
        Membership_Manager::log( sprintf( __( 'Automatisk fornyelse mislykkedes for abonnements-ID: %d. Årsag: %s. Status sat til afventer-annullering.', 'membership-manager' ), $subscription->id, $reason ), 'ERROR' );
        
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
            __( 'Hej %s,<br><br>Din medlemskabsfornyelsesordre er oprettet, men kræver betaling.<br><br>Venligst gennemfør betalingen her: %s<br><br>Ordredetaljer:<br>Ordre #%d<br>Beløb: %s<br><br>Tak!', 'membership-manager' ),
            $user_info->display_name,
            $order->get_checkout_payment_url(),
            $order->get_id(),
            $order->get_formatted_order_total()
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $to, $subject, $message, $headers );
        
        Membership_Manager::log( sprintf( __( 'Sendte betaling påkrævet e-mail til: %s for ordre #%d', 'membership-manager' ), $to, $order->get_id() ) );
    }
    
    /**
     * Send email notification for failed renewal
     */
    private function send_failed_renewal_email( $subscription, $order, $reason ) {
        $user_info = get_userdata( $subscription->user_id );
        $to = $user_info->user_email;
        $subject = __( 'Handling påkrævet: Medlemskabsfornyelse mislykkedes', 'membership-manager' );
        
        $message = sprintf(
            __( 'Hej %s,<br><br>Vi kunne ikke automatisk forny dit medlemskab.<br><br>Venligst opdater din betalingsmetode og gennemfør fornyelsen her: %s<br><br>Hvis du har spørgsmål, kontakt os venligst.<br><br>Tak!', 'membership-manager' ),
            $user_info->display_name,
            $order ? $order->get_checkout_payment_url() : wc_get_account_endpoint_url( 'membership' )
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $to, $subject, $message, $headers );
        
        Membership_Manager::log( sprintf( __( 'Sendte mislykket fornyelsese-mail til: %s', 'membership-manager' ), $to ) );
    }
    
    /**
     * Notify admin about failed renewal
     */
    private function notify_admin_failed_renewal( $subscription, $order, $reason ) {
        $admin_email = get_option( 'admin_email' );
        $subject = __( 'Mislykket medlemskabsfornyelse - Admin notifikation', 'membership-manager' );
        
        $user_info = get_userdata( $subscription->user_id );
        $message = sprintf(
            __( 'En medlemskabsfornyelse er mislykkedes.<br><br>Abonnements-ID: %d<br>Bruger: %s (ID: %d)<br>E-mail: %s<br>Ordre-ID: %s<br>Årsag: %s<br><br>Tag venligst passende handling.', 'membership-manager' ),
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
                        Membership_Manager::log( sprintf( __( 'Oprettede succesfuldt automatisk fornyelsesordre #%d for abonnements-ID: %d', 'membership-manager' ), $order_id, $subscription->id ) );
                    } else {
                        Membership_Manager::log( sprintf( __( 'Kunne ikke oprette automatisk fornyelsesordre for abonnements-ID: %d', 'membership-manager' ), $subscription->id ), 'ERROR' );
                    }
                } else {
                    Membership_Manager::log( sprintf( __( 'Fornyelsesordre eksisterer allerede for abonnements-ID: %d i dag (Ordre #%d)', 'membership-manager' ), $subscription->id, $existing_order ) );
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
