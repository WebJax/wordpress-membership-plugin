<?php
/**
 * Membership Manager Constants
 * 
 * Centralized constants for the plugin to avoid magic numbers and strings
 * throughout the codebase.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Constants {
    
    // Database version
    const DB_VERSION = '1.0.0';
    
    // Membership statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_PENDING_CANCEL = 'pending-cancel';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_ON_HOLD = 'on-hold';
    
    // Renewal types
    const RENEWAL_AUTOMATIC = 'automatic';
    const RENEWAL_MANUAL = 'manual';
    
    // Product types
    const PRODUCT_TYPE_AUTO = 'membership_auto';
    const PRODUCT_TYPE_MANUAL = 'membership_manual';
    
    // Reminder intervals (in days)
    const REMINDER_30_DAYS = 30;
    const REMINDER_14_DAYS = 14;
    const REMINDER_7_DAYS = 7;
    const REMINDER_1_DAY = 1;
    
    // Log types
    const LOG_INFO = 'INFO';
    const LOG_WARNING = 'WARNING';
    const LOG_ERROR = 'ERROR';
    
    // Log file settings
    const LOG_MAX_SIZE = 5242880; // 5MB in bytes
    const LOG_MAX_BACKUPS = 5;
    
    // Default membership duration
    const DEFAULT_MEMBERSHIP_DURATION_YEARS = 1;
    const DEFAULT_MEMBERSHIP_DURATION_MONTHS = 12;
    const DEFAULT_MEMBERSHIP_DURATION_DAYS = 365;
    
    // Cron hook name
    const CRON_HOOK = 'membership_renewal_cron';
    
    // Action hooks
    const HOOK_SUBSCRIPTION_ACTIVATED = 'membership_manager_subscription_activated';
    const HOOK_SUBSCRIPTION_EXPIRED = 'membership_manager_subscription_expired';
    const HOOK_STATUS_CHANGED = 'membership_manager_status_changed';
    const HOOK_PROCESS_RENEWAL_PAYMENT = 'membership_manager_process_renewal_payment';
    const HOOK_FAILED_RENEWAL = 'membership_manager_failed_renewal';
    const HOOK_AFTER_ACTIVATION = 'membership_manager_after_activation';
    const HOOK_AFTER_EXPIRATION = 'membership_manager_after_expiration';
    
    // User meta keys
    const USER_META_HAS_ACTIVE = 'has_active_membership';
    
    // Order meta keys
    const ORDER_META_SUBSCRIPTION_ID = '_membership_subscription_id';
    const ORDER_META_IS_RENEWAL = '_is_membership_renewal';
    
    // Product meta keys
    const PRODUCT_META_TYPE = '_membership_type';
    const PRODUCT_META_AUTO_CHARGE = '_membership_auto_charge';
    const PRODUCT_META_DESCRIPTION = '_membership_description';
    const PRODUCT_META_MIGRATED = '_migrated_from_wc_subscriptions';
    
    // Option keys
    const OPTION_AUTO_PRODUCTS = 'membership_automatic_renewal_products';
    const OPTION_MANUAL_PRODUCTS = 'membership_manual_renewal_products';
    const OPTION_MEMBER_ROLE = 'membership_member_role';
    const OPTION_REMOVE_ROLE_ON_EXP = 'membership_remove_role_on_expiration';
    const OPTION_EMAIL_FROM_NAME = 'membership_email_from_name';
    const OPTION_EMAIL_FROM_ADDRESS = 'membership_email_from_address';
    const OPTION_ENABLE_REMINDERS = 'membership_enable_reminders';
    const OPTION_REMINDER_30_SUBJECT = 'membership_reminder_30_subject';
    const OPTION_REMINDER_14_SUBJECT = 'membership_reminder_14_subject';
    const OPTION_REMINDER_7_SUBJECT = 'membership_reminder_7_subject';
    const OPTION_REMINDER_1_SUBJECT = 'membership_reminder_1_subject';
    const OPTION_ENABLE_WELCOME = 'membership_enable_welcome_email';
    const OPTION_WELCOME_SUBJECT = 'membership_welcome_subject';
    const OPTION_DB_VERSION = 'membership_manager_db_version';
    const OPTION_DURATION_VALUE = 'membership_duration_value';
    const OPTION_DURATION_UNIT = 'membership_duration_unit';
    
    /**
     * Get all valid statuses
     * 
     * @return array
     */
    public static function get_valid_statuses() {
        return array(
            self::STATUS_ACTIVE,
            self::STATUS_EXPIRED,
            self::STATUS_PENDING_CANCEL,
            self::STATUS_CANCELLED,
            self::STATUS_ON_HOLD,
        );
    }
    
    /**
     * Get all valid renewal types
     * 
     * @return array
     */
    public static function get_valid_renewal_types() {
        return array(
            self::RENEWAL_AUTOMATIC,
            self::RENEWAL_MANUAL,
        );
    }
    
    /**
     * Check if status is valid
     * 
     * @param string $status
     * @return bool
     */
    public static function is_valid_status( $status ) {
        return in_array( $status, self::get_valid_statuses(), true );
    }
    
    /**
     * Check if renewal type is valid
     * 
     * @param string $type
     * @return bool
     */
    public static function is_valid_renewal_type( $type ) {
        return in_array( $type, self::get_valid_renewal_types(), true );
    }
    
    /**
     * Get membership duration settings
     * 
     * @return array Array with 'value' and 'unit' keys
     */
    public static function get_membership_duration() {
        $value = get_option( self::OPTION_DURATION_VALUE, self::DEFAULT_MEMBERSHIP_DURATION_YEARS );
        $unit = get_option( self::OPTION_DURATION_UNIT, 'year' );
        
        return array(
            'value' => (int) $value,
            'unit' => $unit,
        );
    }
    
    /**
     * Get valid duration units
     * 
     * @return array
     */
    public static function get_valid_duration_units() {
        return array( 'day', 'week', 'month', 'year' );
    }
    
    /**
     * Apply membership duration to a DateTime object
     * 
     * @param DateTime $date The date to modify
     * @return DateTime Modified date
     */
    public static function apply_membership_duration( $date ) {
        $duration = self::get_membership_duration();
        $value = $duration['value'];
        $unit = $duration['unit'];
        
        // Ensure valid unit
        if ( ! in_array( $unit, self::get_valid_duration_units(), true ) ) {
            $unit = 'year';
            $value = self::DEFAULT_MEMBERSHIP_DURATION_YEARS;
        }
        
        // PHP's DateTime::modify() expects plural forms for values > 1
        $unit_str = ( $value === 1 ) ? $unit : $unit . 's';
        
        $date->modify( "+{$value} {$unit_str}" );
        
        return $date;
    }
}
