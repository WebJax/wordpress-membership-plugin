<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Roles {

    /**
     * Initialize hooks
     */
    public static function init() {
        // Hook into membership activation
        add_action( 'membership_manager_subscription_activated', array( __CLASS__, 'handle_activation' ), 10, 2 );
        
        // Hook into membership expiration
        add_action( 'membership_manager_subscription_expired', array( __CLASS__, 'handle_expiration' ), 10, 2 );
        
        // Hook into membership status changes
        add_action( 'membership_manager_status_changed', array( __CLASS__, 'handle_status_change' ), 10, 3 );
    }

    /**
     * Handle membership activation
     * 
     * @param int $user_id User ID
     * @param int $subscription_id Subscription ID
     */
    public static function handle_activation( $user_id, $subscription_id ) {
        Membership_Manager::log( sprintf( __( 'Håndterer aktivering for bruger-ID: %d, abonnements-ID: %d', 'membership-manager' ), $user_id, $subscription_id ) );
        
        $user = get_user_by( 'ID', $user_id );
        
        if ( ! $user ) {
            Membership_Manager::log( sprintf( __( 'Bruger-ID %d ikke fundet', 'membership-manager' ), $user_id ), 'ERROR' );
            return;
        }
        
        // Get configured member role (default to 'subscriber' if not set)
        $member_role = get_option( 'membership_member_role', 'subscriber' );
        
        // Add member role (doesn't remove other roles)
        $user->add_role( $member_role );
        
        // Set user meta to track membership status
        update_user_meta( $user_id, 'has_active_membership', 'yes' );
        
        Membership_Manager::log( sprintf( __( 'Tilføjede rolle "%s" til bruger-ID: %d', 'membership-manager' ), $member_role, $user_id ) );
        
        // Hook for additional custom actions
        do_action( 'membership_manager_after_activation', $user_id, $subscription_id );
    }

    /**
     * Handle membership expiration
     * 
     * @param int $user_id User ID
     * @param int $subscription_id Subscription ID
     */
    public static function handle_expiration( $user_id, $subscription_id ) {
        Membership_Manager::log( sprintf( __( 'Håndterer udløb for bruger-ID: %d, abonnements-ID: %d', 'membership-manager' ), $user_id, $subscription_id ) );
        
        $user = get_user_by( 'ID', $user_id );
        
        if ( ! $user ) {
            Membership_Manager::log( sprintf( __( 'Bruger-ID %d ikke fundet', 'membership-manager' ), $user_id ), 'ERROR' );
            return;
        }
        
        // Get configured member role
        $member_role = get_option( 'membership_member_role', 'subscriber' );
        
        // Check if we should remove the role on expiration
        $remove_role_on_expiration = get_option( 'membership_remove_role_on_expiration', 'yes' );
        
        if ( $remove_role_on_expiration === 'yes' ) {
            // Remove member role
            $user->remove_role( $member_role );
            
            // Ensure user has at least the default role
            if ( empty( $user->roles ) ) {
                $default_role = get_option( 'default_role', 'subscriber' );
                $user->add_role( $default_role );
            }
            
            Membership_Manager::log( sprintf( __( 'Fjernede rolle "%s" fra bruger-ID: %d', 'membership-manager' ), $member_role, $user_id ) );
        } else {
            Membership_Manager::log( sprintf( __( 'Rolle fjernelse deaktiveret. Rolle "%s" beholdt for bruger-ID: %d', 'membership-manager' ), $member_role, $user_id ) );
        }
        
        // Update user meta
        update_user_meta( $user_id, 'has_active_membership', 'no' );
        
        // Hook for additional custom actions
        do_action( 'membership_manager_after_expiration', $user_id, $subscription_id );
    }

    /**
     * Handle membership status changes
     * 
     * @param int $subscription_id Subscription ID
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    public static function handle_status_change( $subscription_id, $old_status, $new_status ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        $subscription = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $subscription_id
        ) );
        
        if ( ! $subscription ) {
            return;
        }
        
        Membership_Manager::log( sprintf( 
            __( 'Status ændret for abonnements-ID: %d (Bruger: %d) fra "%s" til "%s"', 'membership-manager' ),
            $subscription_id,
            $subscription->user_id,
            $old_status,
            $new_status
        ) );
        
        // Handle activation
        if ( $new_status === 'active' && $old_status !== 'active' ) {
            do_action( 'membership_manager_subscription_activated', $subscription->user_id, $subscription_id );
        }
        
        // Handle expiration
        if ( $new_status === 'expired' && $old_status === 'active' ) {
            do_action( 'membership_manager_subscription_expired', $subscription->user_id, $subscription_id );
        }
        
        // Handle cancellation
        if ( $new_status === 'cancelled' ) {
            self::handle_expiration( $subscription->user_id, $subscription_id );
        }
    }

    /**
     * Check if user has active membership
     * 
     * @param int $user_id User ID
     * @return bool True if user has active membership
     */
    public static function user_has_active_membership( $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM $table_name WHERE user_id = %d",
            $user_id
        ) );
        
        return $status === 'active';
    }

    /**
     * Get all users with active memberships
     * 
     * @return array Array of user IDs
     */
    public static function get_active_members() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        $user_ids = $wpdb->get_col(
            "SELECT user_id FROM $table_name WHERE status = 'active'"
        );
        
        return $user_ids;
    }
}
