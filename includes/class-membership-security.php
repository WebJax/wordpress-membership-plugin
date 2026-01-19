<?php
/**
 * Membership Security Helper
 * 
 * Security utilities for rate limiting, validation, and protection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Security {
    
    /**
     * Rate limit transient prefix
     */
    const RATE_LIMIT_PREFIX = 'membership_rate_limit_';
    
    /**
     * Check and enforce rate limiting for AJAX requests
     * 
     * @param string $action The action being rate limited
     * @param int $max_requests Maximum requests allowed per period
     * @param int $period Time period in seconds (default: 1 hour)
     * @return bool True if within limit, dies if exceeded
     */
    public static function check_rate_limit( $action, $max_requests = 100, $period = HOUR_IN_SECONDS ) {
        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        
        // Use both user ID and IP for rate limiting
        $key = self::RATE_LIMIT_PREFIX . $action . '_' . ( $user_id ?: $ip_address );
        $count = get_transient( $key );
        
        if ( $count === false ) {
            // First request in this period
            set_transient( $key, 1, $period );
            return true;
        }
        
        if ( $count >= $max_requests ) {
            Membership_Manager::log( sprintf(
                'Rate limit exceeded for action "%s" by user %d (IP: %s)',
                $action,
                $user_id,
                $ip_address
            ), 'WARNING' );
            
            wp_send_json_error( array(
                'message' => __( 'Rate limit exceeded. Please try again later.', 'membership-manager' ),
                'retry_after' => self::get_rate_limit_reset_time( $key ),
            ), 429 );
        }
        
        // Increment counter
        set_transient( $key, $count + 1, $period );
        return true;
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    public static function get_client_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP address
        $ip = filter_var( $ip, FILTER_VALIDATE_IP );
        
        return $ip ? $ip : '0.0.0.0';
    }
    
    /**
     * Get time until rate limit resets (made public for testing)
     * 
     * @param string $key Transient key
     * @return int Seconds until reset
     */
    public static function get_rate_limit_reset_time( $key ) {
        global $wpdb;
        
        $timeout = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM $wpdb->options WHERE option_name = %s",
            '_transient_timeout_' . $key
        ) );
        
        if ( $timeout ) {
            return max( 0, $timeout - time() );
        }
        
        return 0;
    }
    
    /**
     * Validate and sanitize admin action
     * 
     * @param string $action Action name
     * @param array $allowed_actions List of allowed actions
     * @return string|false Sanitized action or false if invalid
     */
    public static function validate_admin_action( $action, $allowed_actions = array() ) {
        $action = sanitize_key( $action );
        
        if ( empty( $allowed_actions ) || in_array( $action, $allowed_actions, true ) ) {
            return $action;
        }
        
        Membership_Manager::log( sprintf(
            'Invalid admin action attempted: %s by user %d',
            $action,
            get_current_user_id()
        ), 'WARNING' );
        
        return false;
    }
    
    /**
     * Check if request is from an admin with proper capabilities
     * 
     * @param string $capability Required capability (default: manage_options)
     * @return bool
     */
    public static function is_authorized_admin( $capability = 'manage_options' ) {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        
        if ( ! current_user_can( $capability ) ) {
            Membership_Manager::log( sprintf(
                'Unauthorized access attempt by user %d (missing capability: %s)',
                get_current_user_id(),
                $capability
            ), 'WARNING' );
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify nonce and log failures
     * 
     * @param string $nonce Nonce value
     * @param string $action Nonce action
     * @return bool
     */
    public static function verify_nonce( $nonce, $action ) {
        $valid = wp_verify_nonce( $nonce, $action );
        
        if ( ! $valid ) {
            Membership_Manager::log( sprintf(
                'Nonce verification failed for action "%s" by user %d (IP: %s)',
                $action,
                get_current_user_id(),
                self::get_client_ip()
            ), 'WARNING' );
        }
        
        return $valid;
    }
    
    /**
     * Sanitize array of IDs
     * 
     * @param array $ids Array of IDs
     * @return array Sanitized IDs
     */
    public static function sanitize_ids( $ids ) {
        if ( ! is_array( $ids ) ) {
            return array();
        }
        
        return array_filter( array_map( 'absint', $ids ) );
    }
    
    /**
     * Prevent directory traversal in file paths
     * 
     * @param string $path File path
     * @param string $base_dir Base directory that path must be within
     * @return string|false Sanitized path or false if invalid
     */
    public static function sanitize_file_path( $path, $base_dir ) {
        $real_base = realpath( $base_dir );
        $real_path = realpath( $path );
        
        if ( $real_path === false || strpos( $real_path, $real_base ) !== 0 ) {
            Membership_Manager::log( sprintf(
                'Directory traversal attempt detected: %s (base: %s) by user %d',
                $path,
                $base_dir,
                get_current_user_id()
            ), 'WARNING' );
            return false;
        }
        
        return $real_path;
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event description
     * @param array $context Additional context
     */
    public static function log_security_event( $event, $context = array() ) {
        $log_data = array(
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip' => self::get_client_ip(),
            'timestamp' => current_time( 'mysql' ),
            'context' => $context,
        );
        
        Membership_Manager::log(
            sprintf(
                'SECURITY EVENT: %s | User: %d | IP: %s | Context: %s',
                $event,
                $log_data['user_id'],
                $log_data['ip'],
                wp_json_encode( $context )
            ),
            'WARNING'
        );
        
        // Store in database for security audit
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_security_log';
        
        // Create table if it doesn't exist
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            self::create_security_log_table();
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'event' => $event,
                'user_id' => $log_data['user_id'],
                'ip_address' => $log_data['ip'],
                'context' => wp_json_encode( $context ),
                'created_at' => $log_data['timestamp'],
            ),
            array( '%s', '%d', '%s', '%s', '%s' )
        );
    }
    
    /**
     * Create security log table (protected for override capability)
     */
    protected static function create_security_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_security_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS `" . esc_sql( $table_name ) . "` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            context text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
    
    /**
     * Clear old security logs (keep last 30 days)
     */
    public static function cleanup_security_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_security_log';
        
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            return;
        }
        
        $deleted = $wpdb->query(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        if ( $deleted ) {
            Membership_Manager::log( sprintf(
                'Cleaned up %d old security log entries',
                $deleted
            ) );
        }
    }
}
