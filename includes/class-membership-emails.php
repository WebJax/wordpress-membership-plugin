<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Emails {

    /**
     * Initialize email hooks
     */
    public static function init() {
        // Initialize email queue
        Membership_Email_Queue::init();
    }

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
        
        $subject = get_option( 'membership_welcome_subject', __( 'Velkommen til dit medlemskab!', 'membership-manager' ) );
        
        $message = sprintf(
            __( 'Hej %s,\n\nVelkommen! Dit medlemskab er nu aktivt.\n\nStartdato: %s\nUdløbsdato: %s\nFornyelsestype: %s\n\n', 'membership-manager' ),
            $user->display_name,
            date_i18n( get_option( 'date_format' ), strtotime( $membership->start_date ) ),
            date_i18n( get_option( 'date_format' ), strtotime( $membership->end_date ) ),
            ucfirst( $membership->renewal_type )
        );
        
        if ( $membership->renewal_type === 'manual' && ! empty( $membership->renewal_token ) ) {
            $renewal_link = Membership_Manager::get_renewal_link( $membership );
            $message .= sprintf(
                __( 'Du kan forny dit medlemskab når som helst ved at bruge dette link:\n%s\n\n', 'membership-manager' ),
                $renewal_link
            );
        }
        
        $message .= __( 'Tak for at være medlem!\n', 'membership-manager' );
        
        // Prepare headers
        $headers = self::get_email_headers();
        
        // Enqueue email for async sending
        $result = Membership_Email_Queue::enqueue( $to, $subject, $message, $headers, 'welcome' );
        
        if ( $result ) {
            Membership_Manager::log( 'Queued welcome email to: ' . $to );
        } else {
            Membership_Manager::log( 'Failed to queue welcome email to: ' . $to, 'ERROR' );
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
            Membership_Manager::log( sprintf(__( 'E-mail skabelon ikke fundet: %s', 'membership-manager' ), $template_path), 'ERROR' );
            return;
        }

        if ( ! empty( $to ) && ! empty( $subject ) && ! empty( $message ) ) {
            // Prepare headers
            $headers = self::get_email_headers();
            
            // Enqueue email for async sending
            $result = Membership_Email_Queue::enqueue( $to, $subject, $message, $headers, 'automatic_renewal_reminder_' . $reminder_type );
            
            if ( $result ) {
                Membership_Manager::log( sprintf( __( 'Tilføjede automatisk fornyelsespåmindelse (%s) til kø for: %s', 'membership-manager' ), $reminder_type, $to ) );
            } else {
                Membership_Manager::log( sprintf( __( 'Kunne ikke tilføje automatisk fornyelsespåmindelse (%s) til kø for: %s', 'membership-manager' ), $reminder_type, $to ), 'ERROR' );
            }
        } else {
            Membership_Manager::log( sprintf( __( 'Kunne ikke sende automatisk fornyelsespåmindelse (%s) til: %s. Mangler modtager, emne eller besked.', 'membership-manager' ), $reminder_type, $to ), 'WARNING' );
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
            Membership_Manager::log( sprintf(__( 'E-mail skabelon ikke fundet: %s', 'membership-manager' ), $template_path), 'ERROR' );
            return;
        }

        if ( ! empty( $to ) && ! empty( $subject ) && ! empty( $message ) ) {
            // Prepare headers
            $headers = self::get_email_headers();
            
            // Enqueue email for async sending
            $result = Membership_Email_Queue::enqueue( $to, $subject, $message, $headers, 'manual_renewal_reminder_' . $reminder_type );
            
            if ( $result ) {
                Membership_Manager::log( sprintf( __( 'Tilføjede manuel fornyelsespåmindelse (%s) til kø for: %s', 'membership-manager' ), $reminder_type, $to ) );
            } else {
                Membership_Manager::log( sprintf( __( 'Kunne ikke tilføje manuel fornyelsespåmindelse (%s) til kø for: %s', 'membership-manager' ), $reminder_type, $to ), 'ERROR' );
            }
        } else {
            Membership_Manager::log( sprintf( __( 'Kunne ikke sende manuel fornyelsespåmindelse (%s) til: %s. Mangler modtager, emne eller besked.', 'membership-manager' ), $reminder_type, $to ), 'WARNING' );
        }
    }
    
    /**
     * Get subject line for reminder based on type
     */
    private function get_reminder_subject( $reminder_type ) {
        $subjects = array(
            '30_days' => get_option( 'membership_reminder_30_subject', __( 'Dit medlemskab udløber om 30 dage', 'membership-manager' ) ),
            '14_days' => get_option( 'membership_reminder_14_subject', __( 'Dit medlemskab udløber om 14 dage', 'membership-manager' ) ),
            '7_days' => get_option( 'membership_reminder_7_subject', __( 'Dit medlemskab udløber om 7 dage', 'membership-manager' ) ),
            '1_day' => get_option( 'membership_reminder_1_subject', __( 'Dit medlemskab udløber i morgen', 'membership-manager' ) ),
        );
        
        return isset( $subjects[ $reminder_type ] ) ? $subjects[ $reminder_type ] : __( 'Medlemskabsfornyelsespåmindelse', 'membership-manager' );
    }

    /**
     * Get email headers
     * 
     * @return array Email headers
     */
    private static function get_email_headers() {
        $from_name = get_option( 'membership_email_from_name', get_bloginfo( 'name' ) );
        $from_address = get_option( 'membership_email_from_address', get_option( 'admin_email' ) );
        
        // Validate from address
        if ( ! is_email( $from_address ) ) {
            $from_address = get_option( 'admin_email' );
        }
        
        return array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_address ) . '>'
        );
    }

    /**
     * Send email with proper error handling (Legacy - kept for backward compatibility)
     * Now uses the queue system by default
     * 
     * @param string $to Email recipient
     * @param string $subject Email subject
     * @param string $message Email message
     * @param bool $immediate Set to true to send immediately without queuing (not recommended)
     * @return bool True if email sent/queued successfully, false otherwise
     */
    private function send_email( $to, $subject, $message, $immediate = false ) {
        // Validate email address
        if ( ! is_email( $to ) ) {
            Membership_Manager::log( sprintf( __( 'Ugyldig e-mailadresse: %s', 'membership-manager' ), $to ), 'ERROR' );
            return false;
        }
        
        // Validate subject and message
        if ( empty( $subject ) || empty( $message ) ) {
            Membership_Manager::log( __( 'Tomt emne eller besked i e-mail', 'membership-manager' ), 'ERROR' );
            return false;
        }
        
        // Get headers
        $headers = self::get_email_headers();
        
        // If immediate sending is requested (not recommended), send directly
        if ( $immediate ) {
            // Check for staging mode
            if ( defined( 'MEMBERSHIP_STAGING_MODE' ) && MEMBERSHIP_STAGING_MODE ) {
                Membership_Manager::log( 
                    sprintf( 
                        __( '[STAGING MODE] E-mail blokeret - Til: %s, Emne: %s', 'membership-manager' ), 
                        $to, 
                        $subject 
                    ), 
                    'INFO' 
                );
                return true; // Return true to prevent error logging
            }
            
            $sent = wp_mail( $to, $subject, $message, $headers );
            
            if ( ! $sent ) {
                Membership_Manager::log( sprintf( __( 'Kunne ikke sende e-mail til: %s med emne: %s', 'membership-manager' ), $to, $subject ), 'ERROR' );
            }
            
            return $sent;
        }
        
        // Use queue system by default (recommended)
        return Membership_Email_Queue::enqueue( $to, $subject, $message, $headers, 'legacy' );
    }
}
