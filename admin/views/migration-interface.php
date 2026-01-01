<div class="wrap">
    <h1><?php _e( 'Migrate WooCommerce Subscriptions', 'membership-manager' ); ?></h1>
    
    <?php
    // Show migration result messages
    if ( isset( $_GET['migration'] ) ) {
        if ( $_GET['migration'] === 'success' ) {
            $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Migration completed successfully! %d subscriptions migrated.', 'membership-manager' ), $count ) . '</p></div>';
        } elseif ( $_GET['migration'] === 'error' ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Migration failed. Please check the logs for more details.', 'membership-manager' ) . '</p></div>';
        }
    }
    
    // Show token generation result
    if ( isset( $_GET['tokens_generated'] ) ) {
        $count = absint( $_GET['tokens_generated'] );
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Generated renewal tokens for %d memberships.', 'membership-manager' ), $count ) . '</p></div>';
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
    <p><?php _e( 'Select which subscription products you want to migrate to the membership system. Only subscriptions containing the selected products will be migrated.', 'membership-manager' ); ?></p>
    
    <?php
    // Get all products (not just subscription products)
    $all_products_list = array();
    if ( function_exists( 'wc_get_products' ) ) {
        $products = wc_get_products( array(
            'limit' => -1,
            'status' => 'publish',
        ) );
        
        foreach ( $products as $product ) {
            $all_products_list[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'type' => $product->get_type(),
            );
        }
    }
    ?>
    
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-bottom: 30px;">
        <?php wp_nonce_field( 'migrate_subscriptions_nonce' ); ?>
        <input type="hidden" name="action" value="migrate_subscriptions">
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Select Products to Migrate', 'membership-manager' ); ?></th>
                <td>
                    <?php if ( ! empty( $all_products_list ) ) : ?>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e( 'Select Products', 'membership-manager' ); ?></legend>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php foreach ( $all_products_list as $product ) : ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="migration_products[]" value="<?php echo esc_attr( $product['id'] ); ?>">
                                        <?php echo esc_html( $product['name'] ) . ' (ID: ' . $product['id'] . ' - ' . ucfirst( $product['type'] ) . ')'; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p style="margin-top: 10px;">
                                <button type="button" id="select-all-products" class="button button-secondary"><?php _e( 'Select All', 'membership-manager' ); ?></button>
                                <button type="button" id="deselect-all-products" class="button button-secondary"><?php _e( 'Deselect All', 'membership-manager' ); ?></button>
                            </p>
                        </fieldset>
                        <p class="description"><?php _e( 'Select the products that should be considered membership products. Only subscriptions or orders containing these products will be migrated. Subscription products will automatically be set as automatic renewal.', 'membership-manager' ); ?></p>
                    <?php else : ?>
                        <p><?php _e( 'No products found. Make sure WooCommerce is active and you have products created.', 'membership-manager' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <?php submit_button( __( 'Migrate Selected Subscriptions', 'membership-manager' ), 'primary' ); ?>
    </form>
    
    <script>
    jQuery(document).ready(function($) {
        $('#select-all-products').on('click', function() {
            $('input[name="migration_products[]"]').prop('checked', true);
        });
        
        $('#deselect-all-products').on('click', function() {
            $('input[name="migration_products[]"]').prop('checked', false);
        });
    });
    </script>
    
    <h2><?php _e( 'Generate Renewal Tokens', 'membership-manager' ); ?></h2>
    <p><?php _e( 'If you upgraded from an older version without renewal tokens, use this to generate tokens for existing memberships.', 'membership-manager' ); ?></p>
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block; margin-right: 10px;">
        <?php wp_nonce_field( 'generate_renewal_tokens_nonce' ); ?>
        <input type="hidden" name="action" value="generate_renewal_tokens">
        <?php submit_button( __( 'Generate Missing Tokens', 'membership-manager' ), 'secondary', 'submit', false ); ?>
    </form>
    
    <h2><?php _e( 'Data Cleanup', 'membership-manager' ); ?></h2>
    <p><?php _e( 'If you see invalid dates (like "30. november -0001") in your membership list, use this button to fix them.', 'membership-manager' ); ?></p>
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block;">
        <?php wp_nonce_field( 'cleanup_invalid_dates_nonce' ); ?>
        <input type="hidden" name="action" value="cleanup_invalid_dates">
        <?php submit_button( __( 'Fix Invalid Dates', 'membership-manager' ), 'secondary', 'submit', false ); ?>
    </form>
</div>
