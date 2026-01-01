<div class="wrap">
    <h1><?php _e( 'Migrate WooCommerce Subscriptions', 'membership-manager' ); ?></h1>
    
    <?php
    // Show migration result messages
    if ( isset( $_GET['migration'] ) ) {
        if ( $_GET['migration'] === 'success' ) {
            $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
            $products_converted = isset( $_GET['products_converted'] ) ? absint( $_GET['products_converted'] ) : 0;
            $products_skipped = isset( $_GET['products_skipped'] ) ? absint( $_GET['products_skipped'] ) : 0;
            
            $message = sprintf( __( 'Migration completed successfully! %d subscriptions migrated.', 'membership-manager' ), $count );
            if ( $products_converted > 0 || $products_skipped > 0 ) {
                $message .= '<br>' . sprintf( __( 'Products: %d converted to membership types, %d skipped/already migrated.', 'membership-manager' ), $products_converted, $products_skipped );
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
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
    <p><?php _e( 'Select which subscription products you want to migrate to the membership system. This will:', 'membership-manager' ); ?></p>
    <ul style="list-style: disc; margin-left: 30px; margin-bottom: 20px;">
        <li><?php _e( '<strong>Convert products:</strong> WooCommerce Subscription products will be converted to "Membership Auto" product type, and regular products to "Membership Manual" type.', 'membership-manager' ); ?></li>
        <li><?php _e( '<strong>Auto-configure settings:</strong> Converted products will automatically be added to the appropriate membership renewal lists in settings.', 'membership-manager' ); ?></li>
        <li><?php _e( '<strong>Migrate subscriptions:</strong> All active subscriptions containing the selected products will be migrated to the membership system.', 'membership-manager' ); ?></li>
        <li><?php _e( '<strong>Preserve data:</strong> Original subscription metadata (period, interval, length) will be preserved for reference.', 'membership-manager' ); ?></li>
    </ul>
    
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
                                <?php foreach ( $all_products_list as $product ) : 
                                    $type_badge = '';
                                    if ( $product['type'] === 'membership_auto' ) {
                                        $type_badge = '<span style="background: #00a32a; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">✓ Membership Auto</span>';
                                    } elseif ( $product['type'] === 'membership_manual' ) {
                                        $type_badge = '<span style="background: #2271b1; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">✓ Membership Manual</span>';
                                    } elseif ( $product['type'] === 'subscription' || $product['type'] === 'variable-subscription' ) {
                                        $type_badge = '<span style="background: #f0f0f1; color: #2c3338; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">→ Will convert to Auto</span>';
                                    }
                                ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="migration_products[]" value="<?php echo esc_attr( $product['id'] ); ?>">
                                        <?php echo esc_html( $product['name'] ) . ' (ID: ' . $product['id'] . ')'; ?> <?php echo $type_badge; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p style="margin-top: 10px;">
                                <button type="button" id="select-all-products" class="button button-secondary"><?php _e( 'Select All', 'membership-manager' ); ?></button>
                                <button type="button" id="deselect-all-products" class="button button-secondary"><?php _e( 'Deselect All', 'membership-manager' ); ?></button>
                            </p>
                        </fieldset>
                        <p class="description">
                            <?php _e( '<strong>Product Conversion:</strong>', 'membership-manager' ); ?><br>
                            • <?php _e( 'WooCommerce Subscription products → <strong>Membership Auto</strong> (automatic renewal)', 'membership-manager' ); ?><br>
                            • <?php _e( 'Regular products → <strong>Membership Manual</strong> (manual renewal)', 'membership-manager' ); ?><br>
                            • <?php _e( 'Products already converted will be skipped', 'membership-manager' ); ?>
                        </p>
                    <?php else : ?>
                        <p><?php _e( 'No products found. Make sure WooCommerce is active and you have products created.', 'membership-manager' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <div class="notice notice-info inline" style="margin-top: 0; margin-bottom: 20px;">
            <p>
                <strong><?php _e( 'Note:', 'membership-manager' ); ?></strong>
                <?php _e( 'After migration, you can edit the converted products in WooCommerce → Products to adjust pricing, descriptions, or renewal periods. The products will maintain their new membership type.', 'membership-manager' ); ?>
            </p>
        </div>
        
        <?php submit_button( __( 'Migrate Products & Subscriptions', 'membership-manager' ), 'primary' ); ?>
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
