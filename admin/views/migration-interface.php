<div class="wrap">
    <h1><?php _e( 'Migrate WooCommerce Subscriptions', 'membership-manager' ); ?></h1>
    
    <?php
    // Show migration result messages
    if ( isset( $_GET['migration'] ) ) {
        if ( $_GET['migration'] === 'success' ) {
            $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
            $products_converted = isset( $_GET['products_converted'] ) ? absint( $_GET['products_converted'] ) : 0;
            $products_skipped = isset( $_GET['products_skipped'] ) ? absint( $_GET['products_skipped'] ) : 0;
            
            $message = sprintf( __( 'Migration fuldført succesfuldt! %d abonnementer migreret.', 'membership-manager' ), $count );
            if ( $products_converted > 0 || $products_skipped > 0 ) {
                $message .= '<br>' . sprintf( __( 'Products: %d converted to membership types, %d skipped/already migrated.', 'membership-manager' ), $products_converted, $products_skipped );
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        } elseif ( $_GET['migration'] === 'error' ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Migration mislykkedes. Tjek venligst logs for flere detaljer.', 'membership-manager' ) . '</p></div>';
        }
    }
    
    // Show token generation result
    if ( isset( $_GET['tokens_generated'] ) ) {
        $count = absint( $_GET['tokens_generated'] );
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Genererede fornyelsestokens for %d medlemskaber.', 'membership-manager' ), $count ) . '</p></div>';
    }
    
    // Show cleanup result messages
    if ( isset( $_GET['cleanup'] ) ) {
        if ( $_GET['cleanup'] === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Oprydning af ugyldige datoer fuldført succesfuldt!', 'membership-manager' ) . '</p></div>';
        } elseif ( $_GET['cleanup'] === 'error' ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Oprydning mislykkedes. Tjek venligst logs for flere detaljer.', 'membership-manager' ) . '</p></div>';
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
        <?php submit_button( __( 'Generer manglende tokens', 'membership-manager' ), 'secondary', 'submit', false ); ?>
    </form>
    
    <h2><?php _e( 'Data Cleanup', 'membership-manager' ); ?></h2>
    <p><?php _e( 'If you see invalid dates (like "30. november -0001") in your membership list, use this button to fix them.', 'membership-manager' ); ?></p>
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block;">
        <?php wp_nonce_field( 'cleanup_invalid_dates_nonce' ); ?>
        <input type="hidden" name="action" value="cleanup_invalid_dates">
        <?php submit_button( __( 'Ret ugyldige datoer', 'membership-manager' ), 'secondary', 'submit', false ); ?>
    </form>
    
    <hr style="margin: 30px 0;">
    
    <h2><?php _e( 'Validate Membership Data', 'membership-manager' ); ?></h2>
    <p><?php _e( 'Run a validation check to verify that membership numbers are correct in relation to WooCommerce orders. This will:', 'membership-manager' ); ?></p>
    <ul style="list-style: disc; margin-left: 30px; margin-bottom: 20px;">
        <li><?php _e( 'Check that all completed orders with membership products have corresponding memberships', 'membership-manager' ); ?></li>
        <li><?php _e( 'Verify that memberships have valid associated orders', 'membership-manager' ); ?></li>
        <li><?php _e( 'Identify data inconsistencies between orders and memberships', 'membership-manager' ); ?></li>
        <li><?php _e( 'Generate a detailed report of any issues found', 'membership-manager' ); ?></li>
    </ul>
    
    <?php
    // Display validation results if available
    $validation_param = isset( $_GET['validation'] ) ? sanitize_text_field( $_GET['validation'] ) : '';
    if ( $validation_param === 'completed' ) {
        $validation_results = get_transient( 'membership_validation_results' );
        
        if ( $validation_results ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Validering fuldført succesfuldt!', 'membership-manager' ) . '</p></div>';
            
            // Display statistics
            echo '<div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; border-radius: 4px;">';
            echo '<h3>' . __( 'Validation Summary', 'membership-manager' ) . '</h3>';
            echo '<table class="widefat" style="margin-top: 15px;">';
            echo '<thead><tr><th>' . __( 'Metric', 'membership-manager' ) . '</th><th>' . __( 'Antal', 'membership-manager' ) . '</th></tr></thead>';
            echo '<tbody>';
            echo '<tr><td>' . __( 'Total Orders Checked', 'membership-manager' ) . '</td><td><strong>' . esc_html( $validation_results['total_orders_checked'] ) . '</strong></td></tr>';
            echo '<tr><td>' . __( 'Total Memberships Checked', 'membership-manager' ) . '</td><td><strong>' . esc_html( $validation_results['total_memberships_checked'] ) . '</strong></td></tr>';
            echo '<tr><td style="color: #00a32a;">' . __( 'Orders with Valid Membership', 'membership-manager' ) . '</td><td><strong style="color: #00a32a;">' . esc_html( $validation_results['orders_with_membership'] ) . '</strong></td></tr>';
            echo '<tr><td style="color: #d63638;">' . __( 'Orders Missing Membership', 'membership-manager' ) . '</td><td><strong style="color: #d63638;">' . esc_html( $validation_results['orders_without_membership'] ) . '</strong></td></tr>';
            echo '<tr><td style="color: #00a32a;">' . __( 'Memberships with Order', 'membership-manager' ) . '</td><td><strong style="color: #00a32a;">' . esc_html( $validation_results['memberships_with_order'] ) . '</strong></td></tr>';
            echo '<tr><td style="color: #826eb4;">' . __( 'Orphaned Memberships', 'membership-manager' ) . '</td><td><strong style="color: #826eb4;">' . esc_html( $validation_results['orphaned_memberships'] ) . '</strong></td></tr>';
            echo '<tr><td style="color: #d63638;">' . __( 'Data uoverensstemmelser', 'membership-manager' ) . '</td><td><strong style="color: #d63638;">' . esc_html( $validation_results['data_mismatches'] ) . '</strong></td></tr>';
            echo '</tbody>';
            echo '</table>';
            
            // Display issues if any
            if ( ! empty( $validation_results['issues'] ) ) {
                echo '<h3 style="margin-top: 25px;">' . sprintf( __( 'Issues Found (%d)', 'membership-manager' ), count( $validation_results['issues'] ) ) . '</h3>';
                echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
                
                foreach ( $validation_results['issues'] as $issue ) {
                    $icon = '⚠️';
                    $color = '#f0ad4e';
                    
                    switch ( $issue['type'] ) {
                        case 'error':
                            $icon = '❌';
                            $color = '#d63638';
                            break;
                        case 'warning':
                            $icon = '⚠️';
                            $color = '#f0ad4e';
                            break;
                        case 'info':
                            $icon = 'ℹ️';
                            $color = '#2271b1';
                            break;
                    }
                    
                    echo '<div style="margin-bottom: 10px; padding: 10px; background: #fff; border-left: 4px solid ' . esc_attr( $color ) . ';">';
                    echo '<span style="font-size: 16px;">' . $icon . '</span> ';
                    echo '<strong style="text-transform: uppercase; color: ' . esc_attr( $color ) . ';">' . esc_html( $issue['type'] ) . ':</strong> ';
                    echo esc_html( $issue['message'] );
                    
                    if ( isset( $issue['order_id'] ) ) {
                        $order_edit_url = admin_url( 'post.php?post=' . $issue['order_id'] . '&action=edit' );
                        echo ' <a href="' . esc_url( $order_edit_url ) . '" target="_blank" style="text-decoration: none;">[' . __( 'View Order', 'membership-manager' ) . ']</a>';
                    }
                    
                    if ( isset( $issue['membership_id'] ) ) {
                        $membership_view_url = admin_url( 'admin.php?page=membership-manager&action=view&id=' . $issue['membership_id'] );
                        echo ' <a href="' . esc_url( $membership_view_url ) . '" target="_blank" style="text-decoration: none;">[' . __( 'View Membership', 'membership-manager' ) . ']</a>';
                    }
                    
                    echo '</div>';
                }
                
                echo '</div>';
            } else {
                echo '<div class="notice notice-success inline" style="margin-top: 20px;"><p>✅ <strong>' . __( 'Ingen problemer fundet! Alle medlemskabsdata er konsistente med WooCommerce-ordrer.', 'membership-manager' ) . '</strong></p></div>';
            }
            
            echo '</div>';
            
            // Clear transient after display
            delete_transient( 'membership_validation_results' );
        }
    }
    ?>
    
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block;">
        <?php wp_nonce_field( 'validate_membership_data_nonce' ); ?>
        <input type="hidden" name="action" value="validate_membership_data">
        <?php submit_button( __( 'Kør valideringstjek', 'membership-manager' ), 'secondary', 'submit', false ); ?>
    </form>
    
    <div class="notice notice-info inline" style="margin-top: 15px; max-width: 800px;">
        <p>
            <strong><?php _e( 'Note:', 'membership-manager' ); ?></strong>
            <?php _e( 'This validation is read-only and will not modify any data. It only reports discrepancies for manual review.', 'membership-manager' ); ?>
        </p>
    </div>
</div>
