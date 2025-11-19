<?php

class Membership_Manager {

    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'create_membership_subscription' ), 10, 1 );

        // Schedule cron job
        if ( ! wp_next_scheduled( 'membership_renewal_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'membership_renewal_cron' );
        }

        add_action( 'membership_renewal_cron', array( __CLASS__, 'run_renewal_process' ) );

        // Admin menu
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );

        // AJAX handler
        add_action( 'wp_ajax_filter_memberships', array( __CLASS__, 'filter_memberships' ) );

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );

        // Handle migration
        add_action( 'admin_post_migrate_subscriptions', array( __CLASS__, 'handle_migrate_subscriptions' ) );

        // Handle updates and deletion
        add_action( 'admin_post_update_membership_details', array( __CLASS__, 'handle_update_membership_details' ) );
        add_action( 'admin_post_delete_membership', array( __CLASS__, 'handle_delete_membership' ) );
        add_action( 'admin_post_add_new_membership', array( __CLASS__, 'handle_add_new_membership' ) );
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'membership-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
    }

    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            start_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            end_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status varchar(20) DEFAULT '' NOT NULL,
            renewal_type varchar(20) DEFAULT 'manual' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'membership_renewal_cron' );
    }

    public static function run_renewal_process() {
        self::log( __( 'Starting renewal process.', 'membership-manager' ) );
        $renewals = new Membership_Renewals();
        $renewals->process_membership_renewals();
        self::log( __( 'Finished renewal process.', 'membership-manager' ) );
    }

    public static function add_admin_menu() {
        add_menu_page(
            __( 'Memberships', 'membership-manager' ),
            __( 'Memberships', 'membership-manager' ),
            'manage_options',
            'membership-manager',
            array( __CLASS__, 'render_admin_page' ),
            'dashicons-groups',
            20
        );

        add_submenu_page(
            'membership-manager',
            __( 'Migration', 'membership-manager' ),
            __( 'Migration', 'membership-manager' ),
            'manage_options',
            'membership-migration',
            array( __CLASS__, 'render_migration_page' )
        );

        // Handle cleanup of invalid dates
        add_action( 'admin_post_cleanup_invalid_dates', array( __CLASS__, 'handle_cleanup_invalid_dates' ) );
    }

    public static function render_admin_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        $membership_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        
        if ( $action === 'view' && $membership_id > 0 ) {
            include_once plugin_dir_path( __FILE__ ) . '../admin/views/membership-details.php';
        } elseif ( $action === 'add' ) {
            include_once plugin_dir_path( __FILE__ ) . '../admin/views/add-membership.php';
        } else {
            include_once plugin_dir_path( __FILE__ ) . '../admin/views/memberships-list.php';
        }
    }


    public static function render_migration_page() {
        include_once plugin_dir_path( __FILE__ ) . '../admin/views/migration-interface.php';
    }

    public static function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_membership-manager' !== $hook && 'memberships_page_membership-migration' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'membership-admin', plugin_dir_url( __FILE__ ) . '../admin/js/admin.js', array( 'jquery' ), '1.0.0', true );
        
        // Localize script with nonce for AJAX calls
        wp_localize_script( 'membership-admin', 'membership_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'filter_memberships_nonce' )
        ) );
    }

    public static function filter_memberships() {
        // Verify nonce if provided (optional for read operations)
        if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( $_POST['nonce'], 'filter_memberships_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Get filter parameters
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $renewal_date = isset( $_POST['renewal_date'] ) ? sanitize_text_field( $_POST['renewal_date'] ) : '';
        
        // First get status counts for dashboard
        $status_counts = self::get_membership_status_counts();
        
        // Build query for filtered results
        $where_clauses = array();
        $where_values = array();
        
        if ( ! empty( $status ) ) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $status;
        }
        
        if ( ! empty( $renewal_date ) ) {
            $where_clauses[] = 'DATE(end_date) = %s';
            $where_values[] = $renewal_date;
        }
        
        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }
        
        $query = "SELECT * FROM $table_name $where_sql ORDER BY end_date ASC";
        
        if ( ! empty( $where_values ) ) {
            $memberships = $wpdb->get_results( $wpdb->prepare( $query, $where_values ) );
        } else {
            $memberships = $wpdb->get_results( $query );
        }
        
        // Generate HTML
        $html = '';
        if ( ! empty( $memberships ) ) {
            foreach ( $memberships as $membership ) {
                $user = get_user_by( 'ID', $membership->user_id );
                $user_display = $user ? $user->display_name . ' (' . $membership->user_id . ')' : $membership->user_id;
                
                // Safe date formatting and status logic
                $start_date_formatted = self::format_date_safely( $membership->start_date );
                $end_date_display = self::format_end_date_with_status( $membership );
                
                $html .= '<tr>';
                $html .= '<td>' . esc_html( $user_display ) . '</td>';
                $html .= '<td>' . esc_html( $start_date_formatted ) . '</td>';
                $html .= '<td>' . $end_date_display . '</td>';
                $html .= '<td><span class="status-' . esc_attr( $membership->status ) . '">' . esc_html( self::get_status_display_name( $membership->status ) ) . '</span></td>';
                $html .= '<td>' . esc_html( ucfirst( $membership->renewal_type ) ) . '</td>';
                $html .= '<td>';
                $html .= '<a href="' . admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership->id ) . '" class="button button-small">' . __( 'View Details', 'membership-manager' ) . '</a> ';
                $html .= '<a href="' . wp_nonce_url( admin_url( 'admin-post.php?action=delete_membership&membership_id=' . $membership->id ), 'delete_membership_nonce' ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __( 'Are you sure?', 'membership-manager' ) . '\');" style="color: #a00;">' . __( 'Delete', 'membership-manager' ) . '</a>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="6">' . __( 'No memberships found.', 'membership-manager' ) . '</td></tr>';
        }
        
        wp_send_json_success( array( 
            'html' => $html, 
            'counts' => $status_counts 
        ) );
    }

    public static function handle_migrate_subscriptions() {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'migrate_subscriptions_nonce' ) ) {
            self::log( __( 'Nonce verification failed for migration.', 'membership-manager' ), 'ERROR' );
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        // Get selected products
        $selected_products = isset( $_POST['migration_products'] ) && is_array( $_POST['migration_products'] ) 
            ? array_map( 'absint', $_POST['migration_products'] ) 
            : array();
        
        if ( empty( $selected_products ) ) {
            wp_die( __( 'Please select at least one product to migrate.', 'membership-manager' ) );
        }

        // Get renewal type
        $renewal_type = isset( $_POST['renewal_type'] ) ? sanitize_text_field( $_POST['renewal_type'] ) : 'automatic';

        // Perform migration
        $migrated_count = self::migrate_woocommerce_subscription( $selected_products, $renewal_type );
        
        // Redirect with success/error message
        $redirect_url = add_query_arg( 
            array( 
                'page' => 'membership-migration',
                'migration' => $migrated_count !== false ? 'success' : 'error',
                'count' => $migrated_count
            ),
            admin_url( 'admin.php' )
        );
        
        wp_redirect( $redirect_url );
        exit;
    }

    public static function migrate_woocommerce_subscription( $selected_products = array(), $renewal_type = 'automatic' ) {
        self::log( sprintf( __( 'Starting WooCommerce subscription migration with products: %s', 'membership-manager' ), implode( ', ', $selected_products ) ) );
        
        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            self::log( __( 'WooCommerce Subscriptions not active for migration.', 'membership-manager' ), 'ERROR' );
            return false;
        }

        try {
            $subscriptions = wcs_get_subscriptions( array( 'subscriptions_per_page' => -1 ) );

            global $wpdb;
            $table_name = $wpdb->prefix . 'membership_subscriptions';
            $migrated_count = 0;
            $skipped_count = 0;

            foreach ( $subscriptions as $subscription ) {
                $user_id = $subscription->get_user_id();
                
                // Check if subscription contains any of the selected products
                $has_selected_product = false;
                foreach ( $subscription->get_items() as $item ) {
                    /** @var \WC_Order_Item_Product $item */
                    $product_id = $item->get_product_id();
                    if ( in_array( $product_id, $selected_products ) ) {
                        $has_selected_product = true;
                        break;
                    }
                }
                
                if ( ! $has_selected_product ) {
                    $skipped_count++;
                    self::log( sprintf( __( 'Skipped subscription for user ID %d - does not contain selected products.', 'membership-manager' ), $user_id ) );
                    continue;
                }
                
                $start_date = $subscription->get_date( 'start_date' );
                $end_date = $subscription->get_date( 'end_date' );
                $status = $subscription->get_status();

                // Handle missing or invalid end_date
                if ( empty( $end_date ) || $end_date === '0000-00-00 00:00:00' ) {
                    // If no end date, set it to one year from start date or current date
                    $start_datetime = !empty( $start_date ) ? new DateTime( $start_date ) : new DateTime();
                    $end_datetime = clone $start_datetime;
                    $end_datetime->modify( '+1 year' );
                    $end_date = $end_datetime->format( 'Y-m-d H:i:s' );
                    
                    self::log( sprintf( __( 'Generated end_date for subscription user ID %d: %s', 'membership-manager' ), $user_id, $end_date ) );
                }

                // Check if subscription already exists
                $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );

                if ( ! $existing ) {
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'user_id' => $user_id,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'status' => $status,
                            'renewal_type' => $renewal_type,
                        )
                    );
                    
                    if ( $result !== false ) {
                        $migrated_count++;
                        self::log( sprintf( __( 'Migrated subscription for user ID: %d', 'membership-manager' ), $user_id ) );
                    } else {
                        self::log( sprintf( __( 'Failed to migrate subscription for user ID: %d', 'membership-manager' ), $user_id ), 'ERROR' );
                    }
                } else {
                    self::log( sprintf( __( 'Subscription already exists for user ID: %d. Skipping.', 'membership-manager' ), $user_id ) );
                }
            }

            self::log( sprintf( __( 'Finished WooCommerce subscription migration. Migrated %d subscriptions, skipped %d.', 'membership-manager' ), $migrated_count, $skipped_count ) );
            return $migrated_count;
            
        } catch ( Exception $e ) {
            self::log( sprintf( __( 'Migration failed with error: %s', 'membership-manager' ), $e->getMessage() ), 'ERROR' );
            return false;
        }
    }


    public static function create_membership_subscription( $order_id ) {
        self::log( sprintf( __( 'Creating or extending membership for order ID: %d', 'membership-manager' ), $order_id ) );
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return;
        }

        $user_id = $order->get_user_id();

        if ( ! $user_id ) {
            self::log( sprintf( __( 'No user ID found for order ID: %d. Aborting.', 'membership-manager' ), $order_id ), 'WARNING' );
            return;
        }

        // Check if order contains membership product
        $automatic_products = get_option( 'membership_automatic_renewal_products', array() );
        $manual_products = get_option( 'membership_manual_renewal_products', array() );
        $all_membership_products = array_merge( $automatic_products, $manual_products );
        
        $found_membership_product = false;
        $renewal_type = 'manual';

        foreach ( $order->get_items() as $item ) {
            /** @var \WC_Order_Item_Product $item */
            $product_id = $item->get_product_id();
            if ( in_array( $product_id, $all_membership_products ) ) {
                $found_membership_product = true;
                if ( in_array( $product_id, $automatic_products ) ) {
                    $renewal_type = 'automatic';
                }
                break; 
            }
        }

        if ( ! $found_membership_product ) {
            self::log( sprintf( __( 'Order ID: %d does not contain any membership products. Skipping.', 'membership-manager' ), $order_id ) );
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        // Check for an existing active subscription
        $existing_subscription = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND status = 'active'",
            $user_id
        ) );

        if ( $existing_subscription ) {
            // Extend the existing subscription
            $end_date = new DateTime( $existing_subscription->end_date );
            
            // If subscription was expired but marked active (edge case) or we want to ensure we add to current time if it's in the past?
            // Usually for renewals we add to the existing end date.
            // But if the end date is in the past, we should probably start from today.
            $now = new DateTime();
            if ( $end_date < $now ) {
                $end_date = $now;
            }

            $end_date->modify( '+1 year' );
            
            $wpdb->update(
                $table_name,
                array( 
                    'end_date' => $end_date->format( 'Y-m-d H:i:s' ),
                    'renewal_type' => $renewal_type // Update renewal type based on latest purchase
                ),
                array( 'id' => $existing_subscription->id )
            );
            self::log( sprintf( __( 'Extended membership for user ID: %d', 'membership-manager' ), $user_id ) );
        } else {
            // Create a new subscription
            $start_date = new DateTime();
            $end_date = new DateTime();
            $end_date->modify( '+1 year' );

            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'start_date' => $start_date->format( 'Y-m-d H:i:s' ),
                    'end_date' => $end_date->format( 'Y-m-d H:i:s' ),
                    'status' => 'active',
                    'renewal_type' => $renewal_type,
                )
            );
            self::log( sprintf( __( 'Created new membership for user ID: %d', 'membership-manager' ), $user_id ) );
        }
    }

    public static function get_membership_status_counts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        $counts = array(
            'active' => 0,
            'expired' => 0,
            'pending-cancel' => 0,
            'cancelled' => 0,
            'on-hold' => 0,
            'total' => 0
        );
        
        // Get all status counts
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status"
        );
        
        foreach ( $results as $result ) {
            $status = $result->status;
            $count = $result->count;
            
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ] = $count;
            }
            $counts['total'] += $count;
        }
        
        return $counts;
    }

    public static function get_status_display_name( $status ) {
        $status_names = array(
            'active' => __( 'Active', 'membership-manager' ),
            'expired' => __( 'Expired', 'membership-manager' ),
            'pending-cancel' => __( 'Pending Cancel', 'membership-manager' ),
            'cancelled' => __( 'Cancelled', 'membership-manager' ),
            'on-hold' => __( 'On Hold', 'membership-manager' )
        );
        
        return isset( $status_names[ $status ] ) ? $status_names[ $status ] : ucfirst( $status );
    }

    public static function format_end_date_with_status( $membership ) {
        // If status is active and no end date or end date is far in future, show "Active - No expiration"
        if ( $membership->status === 'active' && ( 
            empty( $membership->end_date ) || 
            $membership->end_date === '0000-00-00 00:00:00' || 
            strtotime( $membership->end_date ) === false 
        ) ) {
            return '<span style="color: #00a32a; font-weight: 600;">' . __( 'Active - No expiration', 'membership-manager' ) . '</span>';
        }
        
        // For non-active statuses, don't show end date prominently
        if ( $membership->status !== 'active' ) {
            return '<span style="color: #646970;">' . self::get_status_display_name( $membership->status ) . '</span>';
        }
        
        // For active with valid end date
        return esc_html( self::format_date_safely( $membership->end_date ) );
    }

    public static function format_date_safely( $date_string ) {
        // Check for invalid or empty dates
        if ( empty( $date_string ) || $date_string === '0000-00-00 00:00:00' || $date_string === '0000-00-00' ) {
            return __( 'No date set', 'membership-manager' );
        }
        
        $timestamp = strtotime( $date_string );
        
        // Check if strtotime failed
        if ( $timestamp === false || $timestamp < 0 ) {
            return __( 'Invalid date', 'membership-manager' );
        }
        
        return date_i18n( get_option( 'date_format' ), $timestamp );
    }

    public static function handle_cleanup_invalid_dates() {
        self::log( 'Cleanup handler called.' );
        
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            self::log( 'Cleanup failed: insufficient permissions', 'ERROR' );
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }
        
        self::log( 'User permissions verified.' );

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cleanup_invalid_dates_nonce' ) ) {
            self::log( 'Cleanup failed: nonce verification failed', 'ERROR' );
            wp_die( 'Security check failed. Please try again.' );
        }
        
        self::log( 'Nonce verified, starting cleanup.' );

        // Perform cleanup
        $result = self::cleanup_invalid_dates();
        
        self::log( sprintf( 'Cleanup completed with result: %s', $result ? 'success' : 'failed' ) );
        
        // Redirect with success/error message
        $redirect_url = add_query_arg( 
            array( 
                'page' => 'membership-migration',
                'cleanup' => $result ? 'success' : 'error'
            ),
            admin_url( 'admin.php' )
        );
        
        wp_redirect( $redirect_url );
        exit;
    }

    public static function cleanup_invalid_dates() {
        self::log( 'Starting cleanup of invalid end dates.' );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        try {
            // Find memberships with invalid end dates
            $query = "SELECT * FROM $table_name 
                     WHERE end_date IS NULL 
                        OR end_date = '' 
                        OR end_date = '0000-00-00 00:00:00' 
                        OR end_date = '0000-00-00'";
            
            self::log( 'Executing query: ' . $query );
            $invalid_memberships = $wpdb->get_results( $query );
            
            if ( $wpdb->last_error ) {
                self::log( 'SQL Error: ' . $wpdb->last_error, 'ERROR' );
                return false;
            }
            
            self::log( 'Found ' . count( $invalid_memberships ) . ' memberships with invalid dates.' );

            $updated_count = 0;
            
            foreach ( $invalid_memberships as $membership ) {
                // Generate a new end date based on start date + 1 year
                $start_datetime = !empty( $membership->start_date ) && $membership->start_date !== '0000-00-00 00:00:00' 
                    ? new DateTime( $membership->start_date ) 
                    : new DateTime();
                    
                $end_datetime = clone $start_datetime;
                $end_datetime->modify( '+1 year' );
                
                $result = $wpdb->update(
                    $table_name,
                    array( 'end_date' => $end_datetime->format( 'Y-m-d H:i:s' ) ),
                    array( 'id' => $membership->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                
                if ( $result !== false ) {
                    $updated_count++;
                    self::log( sprintf( 'Fixed end_date for membership ID %d (user %d): %s', 
                        $membership->id, $membership->user_id, $end_datetime->format( 'Y-m-d H:i:s' ) ) );
                } else {
                    self::log( sprintf( 'Failed to update membership ID %d (user %d)', 
                        $membership->id, $membership->user_id ), 'ERROR' );
                }
            }

            self::log( sprintf( 'Finished cleanup. Updated %d memberships with invalid end dates.', $updated_count ) );
            return true;
            
        } catch ( Exception $e ) {
            self::log( sprintf( 'Cleanup failed with error: %s', $e->getMessage() ), 'ERROR' );
            return false;
        } catch ( Error $e ) {
            self::log( sprintf( 'Cleanup failed with fatal error: %s', $e->getMessage() ), 'ERROR' );
            return false;
        }
    }

    public static function handle_update_membership_details() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update_membership_details_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;
        if ( ! $membership_id ) {
            wp_die( __( 'Invalid membership ID.', 'membership-manager' ) );
        }

        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $renewal_type = isset( $_POST['renewal_type'] ) ? sanitize_text_field( $_POST['renewal_type'] ) : '';

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        $wpdb->update(
            $table_name,
            array(
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status,
                'renewal_type' => $renewal_type
            ),
            array( 'id' => $membership_id )
        );

        self::log( sprintf( __( 'Updated membership ID: %d by user ID: %d', 'membership-manager' ), $membership_id, get_current_user_id() ) );

        wp_redirect( admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership_id . '&updated=true' ) );
        exit;
    }

    public static function handle_delete_membership() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_membership_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $membership_id = isset( $_GET['membership_id'] ) ? absint( $_GET['membership_id'] ) : 0;
        if ( ! $membership_id ) {
            wp_die( __( 'Invalid membership ID.', 'membership-manager' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        $wpdb->delete(
            $table_name,
            array( 'id' => $membership_id )
        );

        self::log( sprintf( __( 'Deleted membership ID: %d by user ID: %d', 'membership-manager' ), $membership_id, get_current_user_id() ) );

        wp_redirect( admin_url( 'admin.php?page=membership-manager&deleted=true' ) );
        exit;
    }

    public static function handle_add_new_membership() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'add_new_membership_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        if ( ! $user_id || ! get_user_by( 'ID', $user_id ) ) {
            wp_die( __( 'Invalid User ID.', 'membership-manager' ) );
        }

        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active';
        $renewal_type = isset( $_POST['renewal_type'] ) ? sanitize_text_field( $_POST['renewal_type'] ) : 'manual';

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        // Check if user already has a membership
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );
        if ( $existing ) {
            wp_die( sprintf( __( 'User ID %d already has a membership (ID: %d). Please edit the existing membership instead.', 'membership-manager' ), $user_id, $existing->id ) );
        }

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status,
                'renewal_type' => $renewal_type
            )
        );

        $membership_id = $wpdb->insert_id;

        self::log( sprintf( __( 'Created new membership ID: %d for user ID: %d by admin.', 'membership-manager' ), $membership_id, $user_id ) );

        wp_redirect( admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership_id . '&created=true' ) );
        exit;
    }

    public static function log( $message, $type = 'INFO' ) {
        $log_file = plugin_dir_path( __FILE__ ) . '../logs/membership.log';
        $timestamp = date( 'Y-m-d H:i:s' );
        $log_message = "[$timestamp] [$type] - $message" . PHP_EOL;
        file_put_contents( $log_file, $log_message, FILE_APPEND );
    }
}
