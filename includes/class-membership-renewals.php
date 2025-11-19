<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Renewals {

    private $emails;

    public function __construct() {
        $this->emails = new Membership_Emails();
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
            $interval = $today->diff( $end_date );
            $days_left = $interval->days;
            
            // Ensure we are looking at future dates for reminders
            if ( $today > $end_date ) {
                continue; 
            }

            $renewal_type = $subscription->renewal_type;

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
