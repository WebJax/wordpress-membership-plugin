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
    
    // Show batch delete result messages
    if ( isset( $_GET['batch_delete'] ) && $_GET['batch_delete'] === 'completed' ) {
        $delete_results = get_transient( 'membership_batch_delete_results' );
        
        if ( $delete_results ) {
            if ( $delete_results['deleted'] > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Batch sletning fuldført! %d ordrer slettet succesfuldt.', 'membership-manager' ), $delete_results['deleted'] ) . '</p></div>';
            }
            
            if ( $delete_results['failed'] > 0 ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( __( 'Kunne ikke slette %d ordrer: ', 'membership-manager' ), $delete_results['failed'] ) . implode( ', ', array_map( function( $id ) { return '#' . $id; }, $delete_results['failed_orders'] ) ) . '</p></div>';
            }
            
            // Clear transient after display
            delete_transient( 'membership_batch_delete_results' );
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
    // Display fix results if available
    $fix_param = isset( $_GET['fix'] ) ? sanitize_text_field( $_GET['fix'] ) : '';
    if ( $fix_param === 'completed' ) {
        $fix_results = get_transient( 'membership_fix_results' );
        
        if ( $fix_results ) {
            if ( $fix_results['success'] && $fix_results['total_fixed'] > 0 ) {
                echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>' . sprintf( __( 'Reparation fuldført! %d problemer rettet.', 'membership-manager' ), $fix_results['total_fixed'] ) . '</strong></p></div>';
            } elseif ( $fix_results['total_fixed'] === 0 ) {
                echo '<div class="notice notice-info is-dismissible"><p>ℹ️ <strong>' . __( 'Ingen problemer fundet der kunne rettes automatisk.', 'membership-manager' ) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>' . __( 'Reparation mislykkedes. Tjek logs for detaljer.', 'membership-manager' ) . '</strong></p></div>';
            }
            
            // Display statistics
            if ( ! empty( $fix_results['fixes'] ) ) {
                echo '<div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; border-radius: 4px;">';
                echo '<h3>' . __( 'Reparationsdetaljer', 'membership-manager' ) . '</h3>';
                echo '<p><strong>' . sprintf( __( 'Total rettelser: %d', 'membership-manager' ), $fix_results['total_fixed'] ) . '</strong></p>';
                echo '<ul style="list-style: disc; margin-left: 20px;">';
                echo '<li>' . sprintf( __( 'Ordrer linket: %d', 'membership-manager' ), $fix_results['orders_linked'] ) . '</li>';
                if ( isset( $fix_results['memberships_created'] ) && $fix_results['memberships_created'] > 0 ) {
                    echo '<li>' . sprintf( __( 'Medlemskaber oprettet: %d', 'membership-manager' ), $fix_results['memberships_created'] ) . '</li>';
                }
                echo '</ul>';
                
                echo '<div style="max-height: 300px; overflow-y: auto; margin-top: 15px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
                foreach ( $fix_results['fixes'] as $fix ) {
                    $icon = '✅';
                    $color = '#00a32a';
                    
                    if ( $fix['type'] === 'error' ) {
                        $icon = '❌';
                        $color = '#d63638';
                    }
                    
                    echo '<div style="margin-bottom: 8px; padding: 8px; background: #fff; border-left: 4px solid ' . esc_attr( $color ) . ';">';
                    echo '<span style="font-size: 16px;">' . $icon . '</span> ';
                    echo esc_html( $fix['message'] );
                    
                    if ( isset( $fix['order_id'] ) ) {
                        $order_edit_url = admin_url( 'post.php?post=' . $fix['order_id'] . '&action=edit' );
                        echo ' <a href="' . esc_url( $order_edit_url ) . '" target="_blank" style="text-decoration: none;">[' . __( 'View Order', 'membership-manager' ) . ']</a>';
                    }
                    
                    if ( isset( $fix['membership_id'] ) ) {
                        $membership_view_url = admin_url( 'admin.php?page=membership-manager&action=view&id=' . $fix['membership_id'] );
                        echo ' <a href="' . esc_url( $membership_view_url ) . '" target="_blank" style="text-decoration: none;">[' . __( 'View Membership', 'membership-manager' ) . ']</a>';
                    }
                    
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            
            // Clear transient after display
            delete_transient( 'membership_fix_results' );
        }
    }
    
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
                // Count issues by type
                $error_count = 0;
                $warning_count = 0;
                $info_count = 0;
                
                foreach ( $validation_results['issues'] as $issue ) {
                    switch ( $issue['type'] ) {
                        case 'error':
                            $error_count++;
                            break;
                        case 'warning':
                            $warning_count++;
                            break;
                        case 'info':
                            $info_count++;
                            break;
                    }
                }
                
                echo '<h3 style="margin-top: 25px;">' . sprintf( __( 'Problemer fundet (%d)', 'membership-manager' ), count( $validation_results['issues'] ) ) . '</h3>';
                echo '<p style="margin: 10px 0 15px 0;">';
                if ( $error_count > 0 ) {
                    echo '<span style="display: inline-block; margin-right: 15px; padding: 5px 10px; background: #fff; border-left: 4px solid #d63638;">❌ <strong>' . sprintf( __( '%d Fejl', 'membership-manager' ), $error_count ) . '</strong></span> ';
                }
                if ( $warning_count > 0 ) {
                    echo '<span style="display: inline-block; margin-right: 15px; padding: 5px 10px; background: #fff; border-left: 4px solid #f0ad4e;">⚠️ <strong>' . sprintf( __( '%d Advarsler', 'membership-manager' ), $warning_count ) . '</strong></span> ';
                }
                if ( $info_count > 0 ) {
                    echo '<span style="display: inline-block; padding: 5px 10px; background: #fff; border-left: 4px solid #2271b1;">ℹ️ <strong>' . sprintf( __( '%d Info', 'membership-manager' ), $info_count ) . '</strong></span>';
                }
                echo '</p>';
                
                // Add batch delete form for warnings
                if ( $warning_count > 0 ) {
                    echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '" id="batch-delete-orders-form" onsubmit="return confirm(\'' . esc_js( __( 'Er du sikker på at du vil slette de valgte ordrer? Dette kan ikke fortrydes!', 'membership-manager' ) ) . '\');">';
                    wp_nonce_field( 'batch_delete_orders_nonce' );
                    echo '<input type="hidden" name="action" value="batch_delete_orders">';
                    echo '<p style="margin-bottom: 10px;">';
                    echo '<button type="button" id="select-all-warnings" class="button button-secondary">' . __( 'Vælg alle advarsler', 'membership-manager' ) . '</button> ';
                    echo '<button type="button" id="deselect-all-warnings" class="button button-secondary">' . __( 'Fravælg alle', 'membership-manager' ) . '</button> ';
                    echo '<button type="submit" class="button button-primary" style="background: #d63638; border-color: #d63638; margin-left: 10px;" disabled id="batch-delete-btn">' . __( 'Slet valgte ordrer', 'membership-manager' ) . '</button>';
                    echo '<span id="selected-count" style="margin-left: 10px; font-weight: bold;"></span>';
                    echo '</p>';
                }
                
                echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
                
                foreach ( $validation_results['issues'] as $idx => $issue ) {
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
                    
                    // Add checkbox for warnings with order_id
                    if ( $issue['type'] === 'warning' && isset( $issue['order_id'] ) ) {
                        echo '<label style="display: inline-block; margin-right: 10px;">';
                        echo '<input type="checkbox" name="order_ids[]" value="' . esc_attr( $issue['order_id'] ) . '" class="warning-checkbox" style="margin: 0; vertical-align: middle;">';
                        echo '</label>';
                    }
                    
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
                
                if ( $warning_count > 0 ) {
                    echo '</form>';
                    
                    // Add JavaScript for checkbox handling
                    echo '<script>
                    jQuery(document).ready(function($) {
                        function updateDeleteButton() {
                            var checked = $(".warning-checkbox:checked").length;
                            $("#selected-count").text(checked > 0 ? "(" + checked + " valgt)" : "");
                            $("#batch-delete-btn").prop("disabled", checked === 0);
                        }
                        
                        $(".warning-checkbox").on("change", updateDeleteButton);
                        
                        $("#select-all-warnings").on("click", function() {
                            $(".warning-checkbox").prop("checked", true);
                            updateDeleteButton();
                        });
                        
                        $("#deselect-all-warnings").on("click", function() {
                            $(".warning-checkbox").prop("checked", false);
                            updateDeleteButton();
                        });
                    });
                    </script>';
                }
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
    
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block; margin-left: 10px;" onsubmit="return confirm('<?php echo esc_js( __( 'Dette vil automatisk rette simple dataproblemer (manglende ordre-links). Vil du fortsætte?', 'membership-manager' ) ); ?>');">
        <?php wp_nonce_field( 'fix_membership_data_nonce' ); ?>
        <input type="hidden" name="action" value="fix_membership_data">
        <?php submit_button( __( 'Ret dataproblemer', 'membership-manager' ), 'primary', 'submit', false ); ?>
    </form>
    
    <div class="notice notice-info inline" style="margin-top: 15px; max-width: 800px;">
        <p>
            <strong><?php _e( 'Note:', 'membership-manager' ); ?></strong>
            <?php _e( 'Denne validering er skrivebeskyttet og vil ikke ændre nogen data. Den rapporterer kun uoverensstemmelser til manuel gennemgang.', 'membership-manager' ); ?>
        </p>
    </div>
</div>
