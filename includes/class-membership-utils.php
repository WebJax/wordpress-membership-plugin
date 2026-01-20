<?php
/**
 * Membership Manager Utilities
 * 
 * Utility functions for validation, sanitization, and common operations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Utils {
    
    /**
     * Sanitize and validate date input
     * 
     * @param string $date Date string
     * @param string $format Expected format (default: Y-m-d H:i:s)
     * @return string|false Sanitized date or false if invalid
     */
    public static function sanitize_date( $date, $format = 'Y-m-d H:i:s' ) {
        if ( empty( $date ) ) {
            return false;
        }
        
        $date = sanitize_text_field( $date );
        $timestamp = strtotime( $date );
        
        if ( $timestamp === false || $timestamp < 0 ) {
            return false;
        }
        
        return date( $format, $timestamp );
    }
    
    /**
     * Validate subscription data
     * 
     * @param array $data Subscription data
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validate_subscription_data( $data ) {
        $errors = array();
        
        // Validate user ID
        if ( empty( $data['user_id'] ) || ! is_numeric( $data['user_id'] ) ) {
            $errors[] = __( 'Ugyldigt bruger-ID', 'membership-manager' );
        } else {
            $user = get_user_by( 'ID', absint( $data['user_id'] ) );
            if ( ! $user ) {
                $errors[] = __( 'Brugeren findes ikke', 'membership-manager' );
            }
        }
        
        // Validate dates
        if ( ! empty( $data['start_date'] ) ) {
            if ( ! self::sanitize_date( $data['start_date'] ) ) {
                $errors[] = __( 'Ugyldig startdato', 'membership-manager' );
            }
        }
        
        if ( ! empty( $data['end_date'] ) ) {
            if ( ! self::sanitize_date( $data['end_date'] ) ) {
                $errors[] = __( 'Ugyldig slutdato', 'membership-manager' );
            }
        }
        
        // Validate status
        if ( ! empty( $data['status'] ) ) {
            if ( ! Membership_Constants::is_valid_status( $data['status'] ) ) {
                $errors[] = __( 'Ugyldig status', 'membership-manager' );
            }
        }
        
        // Validate renewal type
        if ( ! empty( $data['renewal_type'] ) ) {
            if ( ! Membership_Constants::is_valid_renewal_type( $data['renewal_type'] ) ) {
                $errors[] = __( 'Ugyldig fornyelsestype', 'membership-manager' );
            }
        }
        
        // Check if end date is after start date
        if ( ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) ) {
            $start = strtotime( $data['start_date'] );
            $end = strtotime( $data['end_date'] );
            
            if ( $end <= $start ) {
                $errors[] = __( 'Slutdato skal være efter startdato', 'membership-manager' );
            }
        }
        
        return array(
            'valid' => empty( $errors ),
            'errors' => $errors,
        );
    }
    
    /**
     * Calculate days until expiration
     * 
     * @param string $end_date End date string
     * @return int|false Number of days until expiration, or false if invalid
     */
    public static function get_days_until_expiration( $end_date ) {
        $timestamp = strtotime( $end_date );
        
        if ( $timestamp === false || $timestamp < 0 ) {
            return false;
        }
        
        $now = time();
        $diff = $timestamp - $now;
        
        return (int) floor( $diff / DAY_IN_SECONDS );
    }
    
    /**
     * Check if subscription is expiring soon
     * 
     * @param string $end_date End date string
     * @param int $days Number of days threshold
     * @return bool
     */
    public static function is_expiring_soon( $end_date, $days = 30 ) {
        $days_left = self::get_days_until_expiration( $end_date );
        
        if ( $days_left === false ) {
            return false;
        }
        
        return $days_left >= 0 && $days_left <= $days;
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length (default: 32 bytes = 64 hex characters)
     * @return string
     */
    public static function generate_token( $length = 32 ) {
        try {
            return bin2hex( random_bytes( $length ) );
        } catch ( Exception $e ) {
            // Fallback to wp_generate_password if random_bytes fails
            return wp_generate_password( $length * 2, true, true );
        }
    }
    
    /**
     * Format currency amount
     * 
     * @param float $amount
     * @return string
     */
    public static function format_currency( $amount ) {
        if ( function_exists( 'wc_price' ) ) {
            return wc_price( $amount );
        }
        
        return number_format( $amount, 2 );
    }
    
    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    public static function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }
    
    /**
     * Check if WooCommerce Subscriptions is active
     * 
     * @return bool
     */
    public static function is_wc_subscriptions_active() {
        return class_exists( 'WC_Subscriptions' );
    }
    
    /**
     * Get table name with prefix
     * 
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'membership_subscriptions';
    }
    
    /**
     * Get membership by ID with caching
     * 
     * @param int $membership_id
     * @return object|null
     */
    public static function get_membership_cached( $membership_id ) {
        $cache_key = 'membership_' . $membership_id;
        $membership = wp_cache_get( $cache_key, 'membership_manager' );
        
        if ( false === $membership ) {
            global $wpdb;
            $table_name = self::get_table_name();
            
            $membership = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $membership_id
            ) );
            
            if ( $membership ) {
                wp_cache_set( $cache_key, $membership, 'membership_manager', HOUR_IN_SECONDS );
            }
        }
        
        return $membership;
    }
    
    /**
     * Clear membership cache
     * 
     * @param int $membership_id
     */
    public static function clear_membership_cache( $membership_id ) {
        $cache_key = 'membership_' . $membership_id;
        wp_cache_delete( $cache_key, 'membership_manager' );
    }
    
    /**
     * Check if current request is AJAX
     * 
     * @return bool
     */
    public static function is_ajax_request() {
        return defined( 'DOING_AJAX' ) && DOING_AJAX;
    }
    
    /**
     * Check if current request is cron
     * 
     * @return bool
     */
    public static function is_cron_request() {
        return defined( 'DOING_CRON' ) && DOING_CRON;
    }
    
    /**
     * Sanitize HTML content for emails
     * 
     * @param string $content
     * @return string
     */
    public static function sanitize_email_content( $content ) {
        return wp_kses_post( $content );
    }
    
    /**
     * Get plugin version (cached for performance)
     * 
     * @return string
     */
    public static function get_plugin_version() {
        // Use already defined constant if available
        if ( defined( 'MEMBERSHIP_MANAGER_VERSION' ) ) {
            return MEMBERSHIP_MANAGER_VERSION;
        }
        
        // Fallback to reading from file (cached statically)
        static $version = null;
        
        if ( $version === null ) {
            $plugin_data = get_file_data(
                dirname( __DIR__ ) . '/membership-manager.php',
                array( 'Version' => 'Version' )
            );
            $version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '1.0.0';
        }
        
        return $version;
    }
}
