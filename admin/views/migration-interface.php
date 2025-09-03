<div class="wrap">
    <h1><?php _e( 'Migrate WooCommerce Subscriptions', 'membership-manager' ); ?></h1>
    <p><?php _e( 'Click the button below to migrate your existing WooCommerce Subscriptions to the new membership system.', 'membership-manager' ); ?></p>
    <form method="post">
        <?php wp_nonce_field( 'migrate_subscriptions_nonce' ); ?>
        <input type="hidden" name="action" value="migrate_subscriptions">
        <?php submit_button( __( 'Migrate WC Subscriptions', 'membership-manager' ) ); ?>
    </form>
</div>
