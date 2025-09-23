<div class="wrap">
    <h1><?php _e( 'Migrate WooCommerce Subscriptions', 'membership-manager' ); ?></h1>
    
    <?php
    // Show migration result messages
    if ( isset( $_GET['migration'] ) ) {
        if ( $_GET['migration'] === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Migration completed successfully!', 'membership-manager' ) . '</p></div>';
        } elseif ( $_GET['migration'] === 'error' ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Migration failed. Please check the logs for more details.', 'membership-manager' ) . '</p></div>';
        }
    }
    
    // Show cleanup result messages
    if ( isset( $_GET['cleanup'] ) ) {
        if ( $_GET['cleanup'] === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Invalid dates cleanup completed successfully!', 'membership-manager' ) . '</p></div>';
        } elseif ( $_GET['cleanup'] === 'error' ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Cleanup failed. Please check the logs for more details.', 'membership-manager' ) . '</p></div>';
        }
    }
    ?>
    
    <h2><?php _e( 'WooCommerce Subscriptions Migration', 'membership-manager' ); ?></h2>
    <p><?php _e( 'Click the button below to migrate your existing WooCommerce Subscriptions to the new membership system.', 'membership-manager' ); ?></p>
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block; margin-right: 20px;">
        <?php wp_nonce_field( 'migrate_subscriptions_nonce' ); ?>
        <input type="hidden" name="action" value="migrate_subscriptions">
        <?php submit_button( __( 'Migrate WC Subscriptions', 'membership-manager' ), 'primary', 'submit', false ); ?>
    </form>
    
    <h2><?php _e( 'Data Cleanup', 'membership-manager' ); ?></h2>
    <p><?php _e( 'If you see invalid dates (like "30. november -0001") in your membership list, use this button to fix them.', 'membership-manager' ); ?></p>
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block;">
        <?php wp_nonce_field( 'cleanup_invalid_dates_nonce' ); ?>
        <input type="hidden" name="action" value="cleanup_invalid_dates">
        <?php submit_button( __( 'Fix Invalid Dates', 'membership-manager' ), 'secondary', 'submit', false ); ?>
    </form>
</div>
