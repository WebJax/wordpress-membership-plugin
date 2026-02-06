<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Membership Email Queue
 * 
 * Handles async email sending with retry mechanism
 */
class Membership_Email_Queue {

    /**
     * Maximum number of retry attempts
     */
    const MAX_ATTEMPTS = 3;

    /**
     * Maximum age for queued emails (7 days in seconds)
     */
    const MAX_AGE = 604800;

    /**
     * Batch size for processing emails
     */
    const BATCH_SIZE = 10;

    /**
     * Delay in seconds before immediate queue processing
     */
    const IMMEDIATE_PROCESS_DELAY = 60;

    /**
     * Initialize the email queue
     */
    public static function init() {
        // Register cron hook
        add_action( 'membership_process_email_queue', array( __CLASS__, 'process_queue' ) );
        
        // Schedule recurring event if not already scheduled
        if ( ! wp_next_scheduled( 'membership_process_email_queue' ) ) {
            wp_schedule_event( time(), 'hourly', 'membership_process_email_queue' );
        }
    }

    /**
     * Enqueue an email for async sending
     * 
     * @param string $to Email recipient
     * @param string $subject Email subject
     * @param string $message Email message (HTML)
     * @param array $headers Optional email headers
     * @param string $type Optional email type for filtering
     * @return bool True if enqueued successfully
     */
    public static function enqueue( $to, $subject, $message, $headers = array(), $type = 'general' ) {
        // Validate email address
        if ( ! is_email( $to ) ) {
            Membership_Manager::log( sprintf( __( 'Ugyldig e-mailadresse til kø: %s', 'membership-manager' ), $to ), 'ERROR' );
            return false;
        }

        // Validate subject and message
        if ( empty( $subject ) || empty( $message ) ) {
            Membership_Manager::log( __( 'Tomt emne eller besked - kan ikke tilføje til kø', 'membership-manager' ), 'ERROR' );
            return false;
        }

        // Get current queue
        $queue = get_option( 'membership_email_queue', array() );

        // Create email entry
        $email_entry = array(
            'id' => uniqid( 'email_', true ),
            'to' => sanitize_email( $to ),
            'subject' => sanitize_text_field( $subject ),
            'message' => $message,
            'headers' => $headers,
            'type' => sanitize_text_field( $type ),
            'attempts' => 0,
            'queued_at' => time(),
            'last_attempt' => 0,
            'status' => 'pending',
        );

        // Apply filter to allow modification of email data
        $email_entry = apply_filters( 'membership_email_queue_entry', $email_entry, $type );

        // Add to queue
        $queue[] = $email_entry;

        // Save queue
        $updated = update_option( 'membership_email_queue', $queue );

        if ( $updated ) {
            Membership_Manager::log( sprintf( __( 'E-mail tilføjet til kø (Type: %s, Til: %s)', 'membership-manager' ), $type, $to ) );

            // Schedule immediate processing if not already scheduled
            if ( ! wp_next_scheduled( 'membership_process_email_queue' ) ) {
                wp_schedule_single_event( time() + self::IMMEDIATE_PROCESS_DELAY, 'membership_process_email_queue' );
            }

            return true;
        }

        Membership_Manager::log( __( 'Kunne ikke tilføje e-mail til kø', 'membership-manager' ), 'ERROR' );
        return false;
    }

    /**
     * Process the email queue
     */
    public static function process_queue() {
        // Check for staging mode
        if ( defined( 'MEMBERSHIP_STAGING_MODE' ) && MEMBERSHIP_STAGING_MODE ) {
            Membership_Manager::log( __( '[STAGING MODE] E-mail kø behandling sprunget over', 'membership-manager' ), 'INFO' );
            return;
        }

        // Get current queue
        $queue = get_option( 'membership_email_queue', array() );

        if ( empty( $queue ) ) {
            return;
        }

        Membership_Manager::log( sprintf( __( 'Behandler e-mail kø: %d e-mails i kø', 'membership-manager' ), count( $queue ) ) );

        $processed = 0;
        $failed = 0;
        $updated_queue = array();

        foreach ( $queue as $email ) {
            // Skip if we've reached batch limit
            if ( $processed >= self::BATCH_SIZE ) {
                $updated_queue[] = $email;
                continue;
            }

            // Remove old emails
            if ( self::is_expired( $email ) ) {
                Membership_Manager::log( sprintf( __( 'Fjernede udløbet e-mail fra kø: %s', 'membership-manager' ), $email['id'] ), 'WARNING' );
                continue;
            }

            // Skip if max attempts reached
            if ( $email['attempts'] >= self::MAX_ATTEMPTS ) {
                Membership_Manager::log( sprintf( __( 'E-mail nåede maksimalt antal forsøg: %s (Til: %s)', 'membership-manager' ), $email['id'], $email['to'] ), 'ERROR' );
                $email['status'] = 'failed';
                $updated_queue[] = $email;
                continue;
            }

            // Skip if recently attempted (wait at least 5 minutes between retries)
            if ( $email['last_attempt'] > 0 && ( time() - $email['last_attempt'] ) < 300 ) {
                $updated_queue[] = $email;
                continue;
            }

            // Attempt to send email
            $email['attempts']++;
            $email['last_attempt'] = time();

            $sent = self::send_queued_email( $email );

            if ( $sent ) {
                $email['status'] = 'sent';
                Membership_Manager::log( sprintf( __( 'Sendte e-mail fra kø: %s (Til: %s)', 'membership-manager' ), $email['id'], $email['to'] ) );
                $processed++;
                // Don't add to updated_queue - email is successfully sent
            } else {
                $email['status'] = 'retry';
                $updated_queue[] = $email;
                $failed++;
                Membership_Manager::log( sprintf( __( 'Kunne ikke sende e-mail fra kø: %s (Forsøg %d/%d)', 'membership-manager' ), $email['id'], $email['attempts'], self::MAX_ATTEMPTS ), 'WARNING' );
            }
        }

        // Update queue
        update_option( 'membership_email_queue', $updated_queue );

        Membership_Manager::log( sprintf( __( 'E-mail kø behandling færdig: %d sendt, %d fejlede, %d tilbage i kø', 'membership-manager' ), $processed, $failed, count( $updated_queue ) ) );

        // Trigger action for monitoring
        do_action( 'membership_email_queue_processed', $processed, $failed, count( $updated_queue ) );
    }

    /**
     * Send a queued email
     * 
     * @param array $email Email data
     * @return bool True if sent successfully
     */
    private static function send_queued_email( $email ) {
        // Allow filtering of email before sending
        $email = apply_filters( 'membership_email_queue_before_send', $email );

        // Set default headers if not provided
        $headers = isset( $email['headers'] ) && ! empty( $email['headers'] ) ? $email['headers'] : array();

        // Ensure we have proper headers
        if ( empty( $headers ) ) {
            $from_name = get_option( 'membership_email_from_name', get_bloginfo( 'name' ) );
            $from_address = get_option( 'membership_email_from_address', get_option( 'admin_email' ) );

            // Validate from address
            if ( ! is_email( $from_address ) ) {
                $from_address = get_option( 'admin_email' );
            }

            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_address ) . '>'
            );
        }

        // Send email
        $sent = wp_mail( $email['to'], $email['subject'], $email['message'], $headers );

        // Trigger action for logging/monitoring
        do_action( 'membership_email_queue_sent', $email, $sent );

        return $sent;
    }

    /**
     * Check if email has expired
     * 
     * @param array $email Email data
     * @return bool True if expired
     */
    private static function is_expired( $email ) {
        $age = time() - $email['queued_at'];
        return $age > self::MAX_AGE;
    }

    /**
     * Get queue statistics
     * 
     * @return array Queue stats
     */
    public static function get_stats() {
        $queue = get_option( 'membership_email_queue', array() );

        $stats = array(
            'total' => count( $queue ),
            'pending' => 0,
            'retry' => 0,
            'failed' => 0,
        );

        foreach ( $queue as $email ) {
            if ( isset( $email['status'] ) && isset( $stats[ $email['status'] ] ) ) {
                $stats[ $email['status'] ]++;
            }
        }

        return $stats;
    }

    /**
     * Clear the entire queue
     * 
     * @return bool True if cleared successfully
     */
    public static function clear_queue() {
        Membership_Manager::log( __( 'Ryddede e-mail kø manuelt', 'membership-manager' ), 'WARNING' );
        return delete_option( 'membership_email_queue' );
    }

    /**
     * Retry failed emails
     * 
     * @return int Number of emails reset for retry
     */
    public static function retry_failed() {
        $queue = get_option( 'membership_email_queue', array() );
        $reset_count = 0;

        foreach ( $queue as &$email ) {
            if ( isset( $email['status'] ) && $email['status'] === 'failed' && $email['attempts'] < self::MAX_ATTEMPTS ) {
                $email['status'] = 'retry';
                $email['last_attempt'] = 0;
                $reset_count++;
            }
        }

        if ( $reset_count > 0 ) {
            update_option( 'membership_email_queue', $queue );
            Membership_Manager::log( sprintf( __( 'Nulstillede %d fejlede e-mails til retry', 'membership-manager' ), $reset_count ) );

            // Schedule immediate processing
            wp_schedule_single_event( time() + self::IMMEDIATE_PROCESS_DELAY, 'membership_process_email_queue' );
        }

        return $reset_count;
    }

    /**
     * Deactivation cleanup
     */
    public static function deactivate() {
        // Remove scheduled cron
        $timestamp = wp_next_scheduled( 'membership_process_email_queue' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'membership_process_email_queue' );
        }
    }
}
