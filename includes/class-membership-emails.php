<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Emails {

    public function send_automatic_renewal_reminders( $subscription, $reminder_type ) {
        $user_info = get_userdata($subscription->user_id);
        $to = $user_info->user_email;
        $subject = '';
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

        switch ($reminder_type) {
            case '30_days':
                $subject = __( 'Your subscription will renew in 30 days', 'membership-manager' );
                break;
            case '14_days':
                $subject = __( 'Your subscription will renew in 14 days', 'membership-manager' );
                break;
            case '7_days':
                $subject = __( 'Your subscription will renew in 7 days', 'membership-manager' );
                break;
            case '1_day':
                $subject = __( 'Your subscription will renew tomorrow', 'membership-manager' );
                break;
        }

        if ( ! empty( $to ) && ! empty( $subject ) && ! empty( $message ) ) {
            $this->send_email( $to, $subject, $message );
            Membership_Manager::log( sprintf( __( 'Sent automatic renewal reminder (%s) to: %s', 'membership-manager' ), $reminder_type, $to ) );
        } else {
            Membership_Manager::log( sprintf( __( 'Failed to send automatic renewal reminder (%s) to: %s. Missing to, subject, or message.', 'membership-manager' ), $reminder_type, $to ), 'WARNING' );
        }
    }

    public function send_manual_renewal_reminders( $subscription, $reminder_type ) {
        $user_info = get_userdata($subscription->user_id);
        $to = $user_info->user_email;
        $subject = '';
        $message = '';
        $renewal_link = wc_get_checkout_url(); // Or some other renewal link logic

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

        switch ($reminder_type) {
            case '30_days':
                $subject = __( 'Your subscription will expire in 30 days', 'membership-manager' );
                break;
            case '14_days':
                $subject = __( 'Your subscription will expire in 14 days', 'membership-manager' );
                break;
            case '7_days':
                $subject = __( 'Your subscription will expire in 7 days', 'membership-manager' );
                break;
            case '1_day':
                $subject = __( 'Your subscription will expire tomorrow', 'membership-manager' );
                break;
        }

        if ( ! empty( $to ) && ! empty( $subject ) && ! empty( $message ) ) {
            $this->send_email( $to, $subject, $message );
            Membership_Manager::log( sprintf( __( 'Sent manual renewal reminder (%s) to: %s', 'membership-manager' ), $reminder_type, $to ) );
        } else {
            Membership_Manager::log( sprintf( __( 'Failed to send manual renewal reminder (%s) to: %s. Missing to, subject, or message.', 'membership-manager' ), $reminder_type, $to ), 'WARNING' );
        }
    }

    private function send_email( $to, $subject, $message ) {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail( $to, $subject, $message, $headers );
        if ( ! $sent ) {
            Membership_Manager::log( sprintf( __( 'Failed to send email to: %s with subject: %s', 'membership-manager' ), $to, $subject ), 'ERROR' );
        }
    }
}
