<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Membership_Product_Types {

    public static function init() {
        // Register custom product types
        add_filter( 'product_type_selector', array( __CLASS__, 'add_product_types' ) );
        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'add_product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_data' ) );
        
        // Register product classes
        add_action( 'plugins_loaded', array( __CLASS__, 'load_product_classes' ) );
        
        // Display on product page
        add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'display_membership_info' ), 15 );
    }

    /**
     * Add custom product types to WooCommerce
     */
    public static function add_product_types( $types ) {
        $types['membership_auto'] = __( 'Membership (Auto-Renewal)', 'membership-manager' );
        $types['membership_manual'] = __( 'Membership (Manual)', 'membership-manager' );
        
        return $types;
    }

    /**
     * Add product data tab
     */
    public static function add_product_data_tab( $tabs ) {
        $tabs['membership'] = array(
            'label' => __( 'Membership', 'membership-manager' ),
            'target' => 'membership_product_data',
            'class' => array( 'show_if_membership_auto', 'show_if_membership_manual' ),
            'priority' => 25,
        );
        
        return $tabs;
    }

    /**
     * Add product data panel
     */
    public static function add_product_data_panel() {
        global $post;
        ?>
        <div id="membership_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label><?php _e( 'Membership Duration', 'membership-manager' ); ?></label>
                    <span class="description">
                        <?php _e( 'This membership will be valid for 1 year from purchase date.', 'membership-manager' ); ?>
                    </span>
                </p>
                
                <?php
                $product_type = get_post_meta( $post->ID, '_membership_type', true );
                ?>
                
                <p class="form-field show_if_membership_auto">
                    <label>
                        <input type="checkbox" name="_membership_auto_charge" value="yes" <?php checked( get_post_meta( $post->ID, '_membership_auto_charge', true ), 'yes' ); ?>>
                        <?php _e( 'Attempt automatic payment on renewal', 'membership-manager' ); ?>
                    </label>
                    <span class="description">
                        <?php _e( 'If enabled, the system will attempt to charge the customer\'s saved payment method on renewal.', 'membership-manager' ); ?>
                    </span>
                </p>
                
                <p class="form-field">
                    <label for="_membership_description"><?php _e( 'Membership Description', 'membership-manager' ); ?></label>
                    <textarea id="_membership_description" name="_membership_description" class="large-text" rows="3"><?php echo esc_textarea( get_post_meta( $post->ID, '_membership_description', true ) ); ?></textarea>
                    <span class="description">
                        <?php _e( 'Optional description shown on the product page about what this membership includes.', 'membership-manager' ); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('select#product-type').change(function() {
                var product_type = $(this).val();
                
                if (product_type === 'membership_auto' || product_type === 'membership_manual') {
                    $('.show_if_membership_auto, .show_if_membership_manual').show();
                    
                    if (product_type === 'membership_auto') {
                        $('.show_if_membership_auto').show();
                    } else {
                        $('.show_if_membership_auto').hide();
                    }
                    
                    // Hide fields that don't apply to membership products
                    $('.show_if_simple').hide();
                    $('.show_if_variable').hide();
                } else {
                    $('.show_if_membership_auto, .show_if_membership_manual').hide();
                }
            }).change();
        });
        </script>
        <?php
    }

    /**
     * Save product data
     */
    public static function save_product_data( $post_id ) {
        $product_type = isset( $_POST['product-type'] ) ? sanitize_text_field( $_POST['product-type'] ) : '';
        
        if ( $product_type === 'membership_auto' || $product_type === 'membership_manual' ) {
            update_post_meta( $post_id, '_membership_type', $product_type );
            
            $auto_charge = isset( $_POST['_membership_auto_charge'] ) ? 'yes' : 'no';
            update_post_meta( $post_id, '_membership_auto_charge', $auto_charge );
            
            $description = isset( $_POST['_membership_description'] ) ? wp_kses_post( $_POST['_membership_description'] ) : '';
            update_post_meta( $post_id, '_membership_description', $description );
            
            // Automatically add to appropriate product lists in settings
            if ( $product_type === 'membership_auto' ) {
                $automatic_products = get_option( 'membership_automatic_renewal_products', array() );
                if ( ! in_array( $post_id, $automatic_products ) ) {
                    $automatic_products[] = $post_id;
                    update_option( 'membership_automatic_renewal_products', $automatic_products );
                }
            } else {
                $manual_products = get_option( 'membership_manual_renewal_products', array() );
                if ( ! in_array( $post_id, $manual_products ) ) {
                    $manual_products[] = $post_id;
                    update_option( 'membership_manual_renewal_products', $manual_products );
                }
            }
        }
    }

    /**
     * Load product classes
     */
    public static function load_product_classes() {
        if ( ! class_exists( 'WC_Product' ) ) {
            return;
        }
        
        require_once plugin_dir_path( __FILE__ ) . 'products/class-wc-product-membership-auto.php';
        require_once plugin_dir_path( __FILE__ ) . 'products/class-wc-product-membership-manual.php';
    }

    /**
     * Display membership info on product page
     */
    public static function display_membership_info() {
        global $product;
        
        if ( ! $product ) {
            return;
        }
        
        $product_type = $product->get_type();
        
        if ( $product_type !== 'membership_auto' && $product_type !== 'membership_manual' ) {
            return;
        }
        
        $description = get_post_meta( $product->get_id(), '_membership_description', true );
        $is_auto = $product_type === 'membership_auto';
        
        ?>
        <div class="membership-product-info" style="background: #f7f7f7; padding: 20px; border-radius: 4px; margin: 20px 0;">
            <h3 style="margin-top: 0;">
                <span class="dashicons dashicons-groups" style="vertical-align: middle;"></span>
                <?php _e( 'Membership Details', 'membership-manager' ); ?>
            </h3>
            
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                    <strong><?php _e( 'Duration:', 'membership-manager' ); ?></strong>
                    <?php _e( '1 Year from purchase', 'membership-manager' ); ?>
                </li>
                <li style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                    <strong><?php _e( 'Renewal Type:', 'membership-manager' ); ?></strong>
                    <?php 
                    if ( $is_auto ) {
                        _e( 'Automatic - Renews automatically unless cancelled', 'membership-manager' );
                    } else {
                        _e( 'Manual - You will receive renewal reminders', 'membership-manager' );
                    }
                    ?>
                </li>
                <?php if ( $is_auto ): ?>
                <li style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                    <strong><?php _e( 'Payment:', 'membership-manager' ); ?></strong>
                    <?php _e( 'Your saved payment method will be charged automatically', 'membership-manager' ); ?>
                </li>
                <?php endif; ?>
                
                <?php if ( ! empty( $description ) ): ?>
                <li style="padding: 8px 0;">
                    <?php echo wpautop( $description ); ?>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }
}
