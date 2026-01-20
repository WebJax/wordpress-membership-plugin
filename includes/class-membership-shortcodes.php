<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Shortcodes {

    public static function init() {
        add_shortcode( 'member_only', array( __CLASS__, 'member_only_shortcode' ) );
        add_shortcode( 'membership_details', array( __CLASS__, 'membership_details_shortcode' ) );
    }

    public static function member_only_shortcode( $atts, $content = null ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="membership-alert">' . __( 'Du skal være logget ind for at se dette indhold.', 'membership-manager' ) . '</p>';
        }

        $user_id = get_current_user_id();
        if ( self::is_active_member( $user_id ) ) {
            return do_shortcode( $content );
        } else {
            $atts = shortcode_atts( array(
                'message' => __( 'Dette indhold er forbeholdt aktive medlemmer.', 'membership-manager' ),
            ), $atts );
            return '<p class="membership-alert">' . esc_html( $atts['message'] ) . '</p>';
        }
    }

    public static function membership_details_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        $subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );

        if ( ! $subscription ) {
            return '<p>' . __( 'Intet aktivt medlemskab fundet.', 'membership-manager' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="membership-details-box">
            <h3><?php _e( 'Mit medlemskab', 'membership-manager' ); ?></h3>
            <ul>
                <li><strong><?php _e( 'Status:', 'membership-manager' ); ?></strong> <?php echo Membership_Manager::get_status_display_name( $subscription->status ); ?></li>
                <li><strong><?php _e( 'Startdato:', 'membership-manager' ); ?></strong> <?php echo Membership_Manager::format_date_safely( $subscription->start_date ); ?></li>
                <li><strong><?php _e( 'Expiration Date:', 'membership-manager' ); ?></strong> <?php echo Membership_Manager::format_end_date_with_status( $subscription ); ?></li>
            </ul>
            <?php if ( $subscription->status !== 'active' && $subscription->renewal_type === 'manual' ): 
                 $manual_products = get_option( 'membership_manual_renewal_products', array() );
                 if( !empty($manual_products) ){
                     $product_id = $manual_products[0];
                     $renewal_link = add_query_arg( 'add-to-cart', $product_id, wc_get_checkout_url() );
            ?>
                <a href="<?php echo esc_url( $renewal_link ); ?>" class="button renewal-button"><?php _e( 'Forny nu', 'membership-manager' ); ?></a>
            <?php } endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function is_active_member( $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        $status = $wpdb->get_var( $wpdb->prepare( 
            "SELECT status FROM $table_name WHERE user_id = %d AND status = 'active'", 
            $user_id 
        ) );

        return $status === 'active';
    }
}
