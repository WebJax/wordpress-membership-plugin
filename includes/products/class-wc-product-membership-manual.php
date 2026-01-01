<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Membership Manual Renewal Product
 */
class WC_Product_Membership_Manual extends WC_Product {

    /**
     * Product type
     * @var string
     */
    protected $product_type = 'membership_manual';

    /**
     * Constructor
     */
    public function __construct( $product ) {
        parent::__construct( $product );
    }

    /**
     * Get the product type
     */
    public function get_type() {
        return 'membership_manual';
    }

    /**
     * Returns whether this product is virtual
     */
    public function is_virtual() {
        return true;
    }

    /**
     * Returns whether this product is downloadable
     */
    public function is_downloadable() {
        return false;
    }

    /**
     * Returns whether this product needs shipping
     */
    public function needs_shipping() {
        return false;
    }

    /**
     * Get product add to cart text
     */
    public function add_to_cart_text() {
        return __( 'Add membership', 'membership-manager' );
    }

    /**
     * Get product single add to cart text
     */
    public function single_add_to_cart_text() {
        return __( 'Purchase membership', 'membership-manager' );
    }
}
