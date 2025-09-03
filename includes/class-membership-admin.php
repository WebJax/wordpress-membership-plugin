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
                $manual_products = get_option( 'membership_manual_renewal_products', array() );
                if( !empty($manual_products) ){
                    $product_id = $manual_products[0];
                    $renewal_link = add_query_arg( 'add-to-cart', $product_id, wc_get_checkout_url() );
                    echo '<a href="' . esc_url( $renewal_link ) . '" class="button">' . __( 'Renew Membership', 'membership-manager' ) . '</a>';
                }
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
}
