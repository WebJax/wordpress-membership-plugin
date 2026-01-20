<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Emails {

    /**
     * Send welcome email when membership is created
     * 
     * @param WP_User $user The user object
     * @param object $membership The membership object
     * @return bool True if email sent successfully, false otherwise
     */
    public static function send_welcome_email( $user, $membership ) {
        // Validate inputs
        if ( ! $user || ! $membership ) {
            Membership_Manager::log( 'Invalid user or membership object for welcome email', 'ERROR' );
            return false;
        }
        
        // Check if welcome emails are enabled
        if ( get_option( 'membership_enable_welcome_email', 'yes' ) !== 'yes' ) {
            return false;
        }
        
        $to = $user->user_email;
        
        if ( ! is_email( $to ) ) {
            Membership_Manager::log( sprintf( 'Invalid email address for user ID %d: %s', $user->ID, $to ), 'ERROR' );
            return false;
        }
        
        $subject = get_option( 'membership_welcome_subject', __( 'Welcome to Your Membership!', 'membership-manager' ) );
        
        $message = sprintf(
            __( "Hi %s,\n\nWelcome! Your membership is now active.\n\nStart Date: %s\nExpiry Date: %s\nRenewal Type: %s\n\n", 'membership-manager' ),
            $user->display_name,
            date_i18n( get_option( 'date_format' ), strtotime( $membership->start_date ) ),
            date_i18n( get_option( 'date_format' ), strtotime( $membership->end_date ) ),
            ucfirst( $membership->renewal_type )
        );
        
        if ( $membership->renewal_type === 'manual' && ! empty( $membership->renewal_token ) ) {
            $renewal_link = Membership_Manager::get_renewal_link( $membership );
            $message .= sprintf(
                __( "You can renew your membership at any time using this link:\n%s\n\n", 'membership-manager' ),
                $renewal_link
            );
        }
        
        $message .= __( "Thank you for being a member!\n", 'membership-manager' );
        
        $result = self::send_email( $to, $subject, $message );
        
        if ( $result ) {
            Membership_Manager::log( 'Sent welcome email to: ' . $to );
        } else {
            Membership_Manager::log( 'Failed to send welcome email to: ' . $to, 'ERROR' );
        }
        
        return $result;
    }

    public function send_automatic_renewal_reminders( $subscription, $reminder_type ) {
        // Check if reminders are enabled
        if ( get_option( 'membership_enable_reminders', 'yes' ) !== 'yes' ) {
            return;
        }
        
        $user_info = get_userdata($subscription->user_id);
        $to = $user_info->user_email;
        $subject = $this->get_reminder_subject( $reminder_type );
        $message = '';

        $template_path = plugin_dir_path( __FILE__ ) . '../templates/emails/renewal-reminder-' . str_replace('_', '-', $reminder_type) . '.php';

        if ( file_exists( $template_path ) ) {
            ob_start();
            // Pass subscription to the template
            include $template_path;
            $message = ob_get_clean();

            // Replace placeholders
            $message = str_replace('[user_name]', $user_info->display_name, $message);
            $message = str_replace('[end_date]', date_i18n( get_option( 'date_format' ), strtotime( $subscription->end_date ) ), $message);
        } else {
            Membership_Manager::log( sprintf(__( 'Email template not found: %s', 'membership-manager' ), $template_path), 'ERROR' );
            return;
        }

        if ( ! empty( $to ) && ! empty( $subject ) && ! empty( $message ) ) {
            $this->send_email( $to, $subject, $message );
            Membership_Manager::log( sprintf( __( 'Sent automatic renewal reminder (%s) to: %s', 'membership-manager' ), $reminder_type, $to ) );
        } else {
            Membership_Manager::log( sprintf( __( 'Failed to send automatic renewal reminder (%s) to: %s. Missing to, subject, or message.', 'membership-manager' ), $reminder_type, $to ), 'WARNING' );
        }
    }

    public function send_manual_renewal_reminders( $subscription, $reminder_type ) {
        // Check if reminders are enabled
        if ( get_option( 'membership_enable_reminders', 'yes' ) !== 'yes' ) {
            return;
        }
        
        $user_info = get_userdata($subscription->user_id);
        $to = $user_info->user_email;
        $subject = $this->get_reminder_subject( $reminder_type );
        $message = '';
        
        // Generate unique renewal link using token
        $renewal_link = Membership_Manager::get_renewal_link( $subscription );

        $template_path = plugin_dir_path( __FILE__ ) . '../templates/emails/manual-renewal-reminder-' . str_replace('_', '-', $reminder_type) . '.php';

        if ( file_exists( $template_path ) ) {
            ob_start();
            include $template_path;
            $message = ob_get_clean();

            // Replace placeholders
            $message = str_replace('[user_name]', $user_info->display_name, $message);
            $message = str_replace('[end_date]', date_i18n( get_option( 'date_format' ), strtotime( $subscription->end_date ) ), $message);
            $message = str_replace('[renewal_link]', $renewal_link, $message);
        } else {
            Membership_Manager::log( sprintf(__( 'Email template not found: %s', 'membership-manager' ), $template_path), 'ERROR' );
            return;
        }

        if ( ! empty( $to ) && ! empty( $subject ) && ! empty( $message ) ) {
            $this->send_email( $to, $subject, $message );
            Membership_Manager::log( sprintf( __( 'Sent manual renewal reminder (%s) to: %s', 'membership-manager' ), $reminder_type, $to ) );
        } else {
            Membership_Manager::log( sprintf( __( 'Failed to send manual renewal reminder (%s) to: %s. Missing to, subject, or message.', 'membership-manager' ), $reminder_type, $to ), 'WARNING' );
        }
    }
    
    /**
     * Get subject line for reminder based on type
     */
    private function get_reminder_subject( $reminder_type ) {
        $subjects = array(
            '30_days' => get_option( 'membership_reminder_30_subject', __( 'Your membership will expire in 30 days', 'membership-manager' ) ),
            '14_days' => get_option( 'membership_reminder_14_subject', __( 'Your membership will expire in 14 days', 'membership-manager' ) ),
            '7_days' => get_option( 'membership_reminder_7_subject', __( 'Your membership will expire in 7 days', 'membership-manager' ) ),
            '1_day' => get_option( 'membership_reminder_1_subject', __( 'Your membership will expire tomorrow', 'membership-manager' ) ),
        );
        
        return isset( $subjects[ $reminder_type ] ) ? $subjects[ $reminder_type ] : __( 'Membership Renewal Reminder', 'membership-manager' );
    }

    /**
     * Send email with proper error handling
     * 
     * @param string $to Email recipient
     * @param string $subject Email subject
     * @param string $message Email message
     * @return bool True if email sent successfully, false otherwise
     */
    private function send_email( $to, $subject, $message ) {
        // Check for staging mode
        if ( defined( 'MEMBERSHIP_STAGING_MODE' ) && MEMBERSHIP_STAGING_MODE ) {
            Membership_Manager::log( 
                sprintf( 
                    __( '[STAGING MODE] Email blocked - To: %s, Subject: %s', 'membership-manager' ), 
                    $to, 
                    $subject 
                ), 
                'INFO' 
            );
            return true; // Return true to prevent error logging
        }
        
        // Validate email address
        if ( ! is_email( $to ) ) {
            Membership_Manager::log( sprintf( __( 'Invalid email address: %s', 'membership-manager' ), $to ), 'ERROR' );
            return false;
        }
        
        // Validate subject and message
        if ( empty( $subject ) || empty( $message ) ) {
            Membership_Manager::log( __( 'Empty subject or message in email', 'membership-manager' ), 'ERROR' );
            return false;
        }
        
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
        
        $sent = wp_mail( $to, $subject, $message, $headers );
        
        if ( ! $sent ) {
            Membership_Manager::log( sprintf( __( 'Failed to send email to: %s with subject: %s', 'membership-manager' ), $to, $subject ), 'ERROR' );
        }
        
        return $sent;
    }
}
