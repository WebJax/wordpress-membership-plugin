<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Admin {

    public function __construct() {
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_membership_menu_item' ) );
        add_action( 'woocommerce_account_membership_endpoint', array( $this, 'membership_endpoint_content' ) );
        add_action( 'init', array( $this, 'add_membership_endpoint' ) );

        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_action( 'wp_ajax_search_products', array( $this, 'search_products' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        add_action( 'wp_ajax_send_test_membership_email', array( $this, 'send_test_email' ) );
    }

    public function add_membership_endpoint() {
        add_rewrite_endpoint( 'membership', EP_PAGES );
    }

    public function add_membership_menu_item( $items ) {
        $items['membership'] = __( 'Membership', 'membership-manager' );
        return $items;
    }

    public function membership_endpoint_content() {
        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        $subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );

        if ( $subscription ) {
            echo '<h2>' . __( 'Membership Details', 'membership-manager' ) . '</h2>';
            echo '<p><strong>' . __( 'Status:', 'membership-manager' ) . '</strong> ' . esc_html( $subscription->status ) . '</p>';
            echo '<p><strong>' . __( 'Expires:', 'membership-manager' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription->end_date ) ) ) . '</p>';

            if ( $subscription->renewal_type === 'manual' ) {
                $renewal_link = Membership_Manager::get_renewal_link( $subscription );
                echo '<a href="' . esc_url( $renewal_link ) . '" class="button">' . __( 'Renew Membership', 'membership-manager' ) . '</a>';
            }
        } else {
            echo '<p>' . __( 'You do not have an active membership.', 'membership-manager' ) . '</p>';
        }
    }

    public function add_settings_page() {
        add_options_page(
            __( 'Membership Settings', 'membership-manager' ),
            __( 'Membership Settings', 'membership-manager' ),
            'manage_options',
            'membership-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_settings_page() {
        include_once plugin_dir_path( __FILE__ ) . '../admin/views/settings-page.php';
    }

    public function register_settings() {
        register_setting( 'membership_settings', 'membership_automatic_renewal_products', array( 'sanitize_callback' => array( $this, 'sanitize_product_ids' ) ) );
        register_setting( 'membership_settings', 'membership_manual_renewal_products', array( 'sanitize_callback' => array( $this, 'sanitize_product_ids' ) ) );
        register_setting( 'membership_settings', 'membership_member_role' );
        register_setting( 'membership_settings', 'membership_remove_role_on_expiration' );
        
        // Email settings
        register_setting( 'membership_settings', 'membership_email_from_name' );
        register_setting( 'membership_settings', 'membership_email_from_address', 'sanitize_email' );
        register_setting( 'membership_settings', 'membership_enable_reminders' );
        register_setting( 'membership_settings', 'membership_reminder_30_subject' );
        register_setting( 'membership_settings', 'membership_reminder_14_subject' );
        register_setting( 'membership_settings', 'membership_reminder_7_subject' );
        register_setting( 'membership_settings', 'membership_reminder_1_subject' );
    }

    public function sanitize_product_ids( $input ) {
        $sanitized_input = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $product_id ) {
                $sanitized_input[] = absint( $product_id );
            }
        }
        return $sanitized_input;
    }

    public function search_products() {
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $products = wc_get_products( array( 's' => $search, 'limit' => 10 ) );
        $results = array();
        foreach ( $products as $product ) {
            $results[] = array(
                'id' => $product->get_id(),
                'text' => $product->get_name(),
            );
        }
        wp_send_json( array( 'results' => $results ) );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'settings_page_membership-settings' === $hook ) {
            wp_enqueue_script( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array( 'jquery' ), '4.0.13', true );
            wp_enqueue_style( 'select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css' );
            wp_enqueue_script( 'membership-settings', plugin_dir_url( __FILE__ ) . '../admin/js/settings.js', array( 'jquery', 'select2' ), '1.0.0', true );

            $automatic_products_ids = get_option( 'membership_automatic_renewal_products', array() );
            $manual_products_ids = get_option( 'membership_manual_renewal_products', array() );

            $automatic_products = array();
            foreach( $automatic_products_ids as $product_id ){
                $product = wc_get_product( $product_id );
                if( $product ){
                    $automatic_products[] = array( 'id' => $product_id, 'text' => $product->get_name() );
                }
            }

            $manual_products = array();
            foreach( $manual_products_ids as $product_id ){
                $product = wc_get_product( $product_id );
                if( $product ){
                    $manual_products[] = array( 'id' => $product_id, 'text' => $product->get_name() );
                }
            }

            wp_localize_script( 'membership-settings', 'membership_settings', array(
                'automatic_products' => $automatic_products,
                'manual_products' => $manual_products,
                'remove' => __('Remove', 'membership-manager')
            ) );
        }
    }
    
    public function send_test_email() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'send_test_email' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'membership-manager' ) ) );
        }
        
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'membership-manager' ) ) );
        }
        
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'membership-manager' ) ) );
        }
        
        // Get email settings
        $from_name = get_option( 'membership_email_from_name', get_bloginfo( 'name' ) );
        $from_address = get_option( 'membership_email_from_address', get_option( 'admin_email' ) );
        $subject = __( 'Test Email - Membership Manager', 'membership-manager' );
        
        $message = sprintf(
            __( 'This is a test email from Membership Manager.<br><br>From: %s <%s><br>To: %s<br><br>If you received this email, your email settings are configured correctly!<br><br>Current settings:<br>- Email reminders: %s<br>- From name: %s<br>- From address: %s', 'membership-manager' ),
            $from_name,
            $from_address,
            $email,
            get_option( 'membership_enable_reminders', 'yes' ) === 'yes' ? __( 'Enabled', 'membership-manager' ) : __( 'Disabled', 'membership-manager' ),
            $from_name,
            $from_address
        );
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_address . '>'
        );
        
        $sent = wp_mail( $email, $subject, $message, $headers );
        
        if ( $sent ) {
            Membership_Manager::log( sprintf( __( 'Test email sent to: %s', 'membership-manager' ), $email ) );
            wp_send_json_success( array( 'message' => __( 'Test email sent successfully! Check your inbox.', 'membership-manager' ) ) );
        } else {
            Membership_Manager::log( sprintf( __( 'Failed to send test email to: %s', 'membership-manager' ), $email ), 'ERROR' );
            wp_send_json_error( array( 'message' => __( 'Failed to send test email. Check your server email configuration.', 'membership-manager' ) ) );
        }
    }
}
