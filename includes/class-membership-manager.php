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
        add_action( 'admin_post_generate_renewal_tokens', array( __CLASS__, 'handle_generate_renewal_tokens' ) );
        add_action( 'admin_post_pause_membership', array( __CLASS__, 'handle_pause_membership' ) );
        add_action( 'admin_post_resume_membership', array( __CLASS__, 'handle_resume_membership' ) );
        
        // Register renewal endpoint
        add_action( 'init', array( __CLASS__, 'register_renewal_endpoint' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_renewal_token' ) );
        
        // Handle cleanup of invalid dates
        add_action( 'admin_post_cleanup_invalid_dates', array( __CLASS__, 'handle_cleanup_invalid_dates' ) );
        
        // Handle validation check
        add_action( 'admin_post_validate_membership_data', array( __CLASS__, 'handle_validate_membership_data' ) );
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
            renewal_token varchar(64) DEFAULT '' NOT NULL,
            paused_date datetime DEFAULT NULL,
            status_changed_date datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY renewal_token (renewal_token),
            KEY status (status),
            KEY end_date (end_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $result = dbDelta( $sql );

        // Store database version for future upgrades
        update_option( 'membership_manager_db_version', '1.0.0' );

        // Log activation
        self::log( sprintf( __( 'Plugin activated. Database result: %s', 'membership-manager' ), print_r( $result, true ) ) );

        // Ensure log directory exists
        $log_dir = plugin_dir_path( __FILE__ ) . '../logs';
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }

        // Create .htaccess to protect logs directory
        $htaccess_file = $log_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $bytes_written = file_put_contents( $htaccess_file, "Deny from all\n" );
            if ( false === $bytes_written ) {
                self::log(
                    sprintf(
                        __( 'Warning: Failed to create .htaccess file in logs directory (%s). Please check directory permissions.', 'membership-manager' ),
                        $log_dir
                    ),
                    'WARNING'
                );
            }
        }

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
        
        // Enqueue CSS
        wp_enqueue_style( 'membership-admin', plugin_dir_url( __FILE__ ) . '../admin/css/admin.css', array(), '1.0.0' );
        
        // Enqueue JS
        wp_enqueue_script( 'membership-admin', plugin_dir_url( __FILE__ ) . '../admin/js/admin.js', array( 'jquery' ), '1.0.0', true );
        
        // Localize script with nonce for AJAX calls
        wp_localize_script( 'membership-admin', 'membership_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'filter_memberships_nonce' )
        ) );
    }

    public static function filter_memberships() {
        // Apply rate limiting with higher limits for admin users
        $max_requests = current_user_can( 'manage_options' ) ? 500 : 100;
        Membership_Security::check_rate_limit( 'filter_memberships', $max_requests, HOUR_IN_SECONDS );
        
        // Verify nonce if provided (optional for read operations)
        if ( isset( $_POST['nonce'] ) && ! Membership_Security::verify_nonce( $_POST['nonce'], 'filter_memberships_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Get filter parameters
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $renewal_date = isset( $_POST['renewal_date'] ) ? sanitize_text_field( $_POST['renewal_date'] ) : '';
        
        // Get pagination parameters
        $page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page = 25; // Items per page
        $offset = ( $page - 1 ) * $per_page;
        
        // Get sort parameters
        $sort_column = isset( $_POST['sort_column'] ) ? sanitize_text_field( $_POST['sort_column'] ) : 'end_date';
        $sort_order = isset( $_POST['sort_order'] ) ? sanitize_text_field( $_POST['sort_order'] ) : 'ASC';
        
        // Validate sort column
        $allowed_columns = array( 'user_id', 'start_date', 'end_date', 'status' );
        if ( ! in_array( $sort_column, $allowed_columns ) ) {
            $sort_column = 'end_date';
        }
        
        // Validate sort order
        $sort_order = strtoupper( $sort_order );
        if ( ! in_array( $sort_order, array( 'ASC', 'DESC' ) ) ) {
            $sort_order = 'ASC';
        }
        
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
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM $table_name $where_sql";
        if ( ! empty( $where_values ) ) {
            $total_items = $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) );
        } else {
            $total_items = $wpdb->get_var( $count_query );
        }
        
        $total_pages = ceil( $total_items / $per_page );
        
        // Main query with pagination
        $query = "SELECT * FROM $table_name $where_sql ORDER BY $sort_column $sort_order LIMIT %d OFFSET %d";
        
        if ( ! empty( $where_values ) ) {
            $where_values[] = $per_page;
            $where_values[] = $offset;
            $memberships = $wpdb->get_results( $wpdb->prepare( $query, $where_values ) );
        } else {
            $memberships = $wpdb->get_results( $wpdb->prepare( $query, $per_page, $offset ) );
        }
        
        // FIX N+1 QUERY: Get all users at once
        $user_ids = array();
        if ( ! empty( $memberships ) ) {
            foreach ( $memberships as $membership ) {
                $user_ids[] = $membership->user_id;
            }
            $user_ids = array_unique( $user_ids );
        }
        
        // Load all users in one query
        $users_by_id = array();
        if ( ! empty( $user_ids ) ) {
            $users = get_users( array( 'include' => $user_ids ) );
            foreach ( $users as $user ) {
                $users_by_id[ $user->ID ] = $user;
            }
        }
        
        // Generate HTML
        $html = '';
        if ( ! empty( $memberships ) ) {
            foreach ( $memberships as $membership ) {
                // Use pre-loaded users instead of querying each time
                $user = isset( $users_by_id[ $membership->user_id ] ) ? $users_by_id[ $membership->user_id ] : null;
                $user_display = $user ? $user->display_name . ' (' . $membership->user_id . ')' : $membership->user_id;
                
                // Get My Account URL for the user - use a special preview parameter
                $my_account_url = '';
                if ( function_exists( 'wc_get_page_permalink' ) && $user ) {
                    $base_url = wc_get_page_permalink( 'myaccount' );
                    // Add membership endpoint to show membership details
                    $my_account_url = trailingslashit( $base_url ) . 'membership/';
                    // Add admin preview parameter to potentially show as this user
                    $my_account_url = add_query_arg( 'preview_user', $membership->user_id, $my_account_url );
                }
                
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
                $html .= '<a href="' . admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership->id ) . '" class="button button-small">' . __( 'View', 'membership-manager' ) . '</a> ';
                
                // Add "View My Account" button if WooCommerce is active and user exists
                if ( ! empty( $my_account_url ) && $user ) {
                    $html .= '<a href="' . esc_url( $my_account_url ) . '" class="button button-small" target="_blank" title="' . esc_attr__( 'View customer membership page', 'membership-manager' ) . '"><span class="dashicons dashicons-admin-users" style="font-size: 14px; line-height: 1.4;"></span></a> ';
                }
                
                $html .= '<a href="' . wp_nonce_url( admin_url( 'admin-post.php?action=delete_membership&membership_id=' . $membership->id ), 'delete_membership_nonce' ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . __( 'Are you sure?', 'membership-manager' ) . '\');" style="color: #a00;"><span class="dashicons dashicons-trash"></span></a>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="6">' . __( 'No memberships found.', 'membership-manager' ) . '</td></tr>';
        }
        
        wp_send_json_success( array( 
            'html' => $html, 
            'counts' => $status_counts,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total_items,
                'per_page' => $per_page
            )
        ) );
    }

    /**
     * Get a single membership by ID
     */
    public static function get_membership( $membership_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $membership_id
        ) );
    }

    /**
     * Get user's membership
     */
    public static function get_user_membership( $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY end_date DESC LIMIT 1",
            $user_id
        ) );
    }

    /**
     * Parse subscription order notes to extract status change dates
     * 
     * @param WC_Subscription $subscription The subscription object
     * @return array Array with 'paused_date' and 'status_changed_date'
     */
    public static function parse_subscription_status_dates( $subscription ) {
        $dates = array(
            'paused_date' => null,
            'status_changed_date' => null,
        );
        
        if ( ! function_exists( 'wc_get_order_notes' ) ) {
            return $dates;
        }
        
        try {
            // Get all order notes for this subscription
            $notes = wc_get_order_notes( array(
                'order_id' => $subscription->get_id(),
                'type'     => 'internal',
                'orderby'  => 'date_created',
                'order'    => 'DESC', // Most recent first
            ) );
            
            if ( empty( $notes ) ) {
                return $dates;
            }
            
            // Current status to check for on-hold
            $current_status = $subscription->get_status();
            
            foreach ( $notes as $note ) {
                $content = strtolower( $note->content );
                $note_date = $note->date_created->date( 'Y-m-d H:i:s' );
                
                // Check for status change to on-hold (multiple language variations)
                if ( ! $dates['paused_date'] && $current_status === 'on-hold' ) {
                    if ( strpos( $content, 'to on-hold' ) !== false || 
                         strpos( $content, 'to on hold' ) !== false ||
                         strpos( $content, 'status set to on-hold' ) !== false ) {
                        $dates['paused_date'] = $note_date;
                        self::log( sprintf( 'Found paused_date from order notes for subscription #%d: %s', $subscription->get_id(), $note_date ) );
                    }
                }
                
                // Get the most recent status change (first one we encounter since we're DESC)
                if ( ! $dates['status_changed_date'] ) {
                    if ( strpos( $content, 'status changed' ) !== false || 
                         strpos( $content, 'status set to' ) !== false ) {
                        $dates['status_changed_date'] = $note_date;
                        self::log( sprintf( 'Found status_changed_date from order notes for subscription #%d: %s', $subscription->get_id(), $note_date ) );
                    }
                }
                
                // Break if we found both dates
                if ( $dates['paused_date'] && $dates['status_changed_date'] ) {
                    break;
                }
            }
            
        } catch ( Exception $e ) {
            self::log( sprintf( 'Error parsing order notes for subscription #%d: %s', $subscription->get_id(), $e->getMessage() ), 'WARNING' );
        }
        
        return $dates;
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

        // Perform migration
        $migration_results = self::migrate_woocommerce_subscription( $selected_products );
        
        // Prepare redirect URL parameters
        $redirect_params = array( 'page' => 'membership-migration' );
        
        if ( $migration_results !== false ) {
            $redirect_params['migration'] = 'success';
            $redirect_params['count'] = $migration_results['subscriptions'];
            $redirect_params['products_converted'] = $migration_results['products']['converted'];
            $redirect_params['products_skipped'] = $migration_results['products']['skipped'] + $migration_results['products']['already_migrated'];
        } else {
            $redirect_params['migration'] = 'error';
        }
        
        // Redirect with success/error message
        $redirect_url = add_query_arg( $redirect_params, admin_url( 'admin.php' ) );
        
        wp_redirect( $redirect_url );
        exit;
    }

    public static function migrate_woocommerce_subscription( $selected_products = array() ) {
        self::log( sprintf( __( 'Starting WooCommerce subscription migration with products: %s', 'membership-manager' ), implode( ', ', $selected_products ) ) );
        
        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            self::log( __( 'WooCommerce Subscriptions not active for migration.', 'membership-manager' ), 'ERROR' );
            return false;
        }

        try {
            // First, migrate products to new product types
            $product_migration_results = self::migrate_subscription_products( $selected_products );
            self::log( sprintf( __( 'Product migration completed: %d products converted', 'membership-manager' ), $product_migration_results['converted'] ) );
            
            $subscriptions = wcs_get_subscriptions( array( 'subscriptions_per_page' => -1 ) );

            global $wpdb;
            $table_name = $wpdb->prefix . 'membership_subscriptions';
            $migrated_count = 0;
            $skipped_count = 0;

            foreach ( $subscriptions as $subscription ) {
                $user_id = $subscription->get_user_id();
                
                // Check if subscription contains any of the selected products and determine renewal type
                $has_selected_product = false;
                $renewal_type = 'manual';
                
                foreach ( $subscription->get_items() as $item ) {
                    /** @var \WC_Order_Item_Product $item */
                    $product_id = $item->get_product_id();
                    
                    if ( in_array( $product_id, $selected_products ) ) {
                        $has_selected_product = true;
                        
                        // Check if product is a subscription product
                        $product = $item->get_product();
                        if ( $product && \WC_Subscriptions_Product::is_subscription( $product ) ) {
                            $renewal_type = 'automatic';
                            self::log( sprintf( __( 'Product ID %d is a subscription product - setting as automatic renewal.', 'membership-manager' ), $product_id ) );
                        } else {
                            $renewal_type = 'manual';
                        }
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
                
                // Parse order notes to extract status change dates
                $status_dates = self::parse_subscription_status_dates( $subscription );
                $paused_date = $status_dates['paused_date'];
                $status_changed_date = $status_dates['status_changed_date'];

                // Check if subscription already exists
                $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );

                if ( ! $existing ) {
                    $insert_data = array(
                        'user_id' => $user_id,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'status' => $status,
                        'renewal_type' => $renewal_type,
                    );
                    
                    // Add optional date fields if found
                    if ( $paused_date ) {
                        $insert_data['paused_date'] = $paused_date;
                    }
                    if ( $status_changed_date ) {
                        $insert_data['status_changed_date'] = $status_changed_date;
                    }
                    
                    $result = $wpdb->insert( $table_name, $insert_data );
                    
                    if ( $result !== false ) {
                        $migrated_count++;
                        $log_msg = sprintf( __( 'Migrated subscription for user ID: %d with renewal type: %s', 'membership-manager' ), $user_id, $renewal_type );
                        if ( $paused_date || $status_changed_date ) {
                            $log_msg .= ' (with status dates from order notes)';
                        }
                        self::log( $log_msg );
                    } else {
                        self::log( sprintf( __( 'Failed to migrate subscription for user ID: %d', 'membership-manager' ), $user_id ), 'ERROR' );
                    }
                } else {
                    self::log( sprintf( __( 'Subscription already exists for user ID: %d. Skipping.', 'membership-manager' ), $user_id ) );
                }
            }

            self::log( sprintf( __( 'Finished WooCommerce subscription migration. Migrated %d subscriptions, skipped %d.', 'membership-manager' ), $migrated_count, $skipped_count ) );
            
            return array(
                'subscriptions' => $migrated_count,
                'products' => $product_migration_results
            );
            
        } catch ( Exception $e ) {
            self::log( sprintf( __( 'Migration failed with error: %s', 'membership-manager' ), $e->getMessage() ), 'ERROR' );
            return false;
        }
    }

    /**
     * Migrate WooCommerce Subscription products to our custom product types
     * 
     * @param array $selected_products Array of product IDs to migrate
     * @return array Results with 'converted' and 'skipped' counts
     */
    public static function migrate_subscription_products( $selected_products = array() ) {
        $results = array(
            'converted' => 0,
            'skipped' => 0,
            'already_migrated' => 0,
        );
        
        if ( empty( $selected_products ) ) {
            self::log( __( 'No products selected for migration.', 'membership-manager' ) );
            return $results;
        }
        
        foreach ( $selected_products as $product_id ) {
            $product = wc_get_product( $product_id );
            
            if ( ! $product ) {
                self::log( sprintf( __( 'Product ID %d not found. Skipping.', 'membership-manager' ), $product_id ), 'WARNING' );
                $results['skipped']++;
                continue;
            }
            
            $current_type = $product->get_type();
            
            // Skip if already our custom type
            if ( $current_type === 'membership_auto' || $current_type === 'membership_manual' ) {
                self::log( sprintf( __( 'Product ID %d is already a membership product type. Skipping.', 'membership-manager' ), $product_id ) );
                $results['already_migrated']++;
                continue;
            }
            
            // Determine new product type based on WooCommerce Subscriptions
            $new_type = 'membership_manual'; // Default to manual
            $is_subscription = false;
            
            if ( class_exists( 'WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
                $is_subscription = true;
                $new_type = 'membership_auto';
                self::log( sprintf( __( 'Product ID %d is a WooCommerce subscription - converting to membership_auto', 'membership-manager' ), $product_id ) );
            } else {
                self::log( sprintf( __( 'Product ID %d is not a subscription - converting to membership_manual', 'membership-manager' ), $product_id ) );
            }
            
            // Get subscription metadata to preserve
            $metadata_to_preserve = array();
            if ( $is_subscription ) {
                // Preserve subscription period, interval, length
                $subscription_period = get_post_meta( $product_id, '_subscription_period', true );
                $subscription_period_interval = get_post_meta( $product_id, '_subscription_period_interval', true );
                $subscription_length = get_post_meta( $product_id, '_subscription_length', true );
                
                if ( $subscription_period ) {
                    $metadata_to_preserve['_original_subscription_period'] = $subscription_period;
                }
                if ( $subscription_period_interval ) {
                    $metadata_to_preserve['_original_subscription_interval'] = $subscription_period_interval;
                }
                if ( $subscription_length ) {
                    $metadata_to_preserve['_original_subscription_length'] = $subscription_length;
                }
            }
            
            // Update product type
            wp_set_object_terms( $product_id, $new_type, 'product_type' );
            
            // Update meta to match our custom type
            update_post_meta( $product_id, '_membership_type', $new_type );
            
            // Set product as virtual (memberships don't need shipping)
            update_post_meta( $product_id, '_virtual', 'yes' );
            
            // Save preserved metadata with prefix to avoid conflicts
            foreach ( $metadata_to_preserve as $key => $value ) {
                update_post_meta( $product_id, $key, $value );
            }
            
            // Add migration flag
            update_post_meta( $product_id, '_migrated_from_wc_subscriptions', current_time( 'mysql' ) );
            
            // For auto renewal, set default renewal period if not set
            if ( $new_type === 'membership_auto' ) {
                $renewal_period = get_post_meta( $product_id, '_membership_renewal_period', true );
                if ( empty( $renewal_period ) ) {
                    update_post_meta( $product_id, '_membership_renewal_period', '1' );
                    update_post_meta( $product_id, '_membership_renewal_unit', 'year' );
                    self::log( sprintf( __( 'Set default renewal period (1 year) for product ID %d', 'membership-manager' ), $product_id ) );
                }
            }
            
            // Clear product cache
            wc_delete_product_transients( $product_id );
            
            // Add product to appropriate settings list
            if ( $new_type === 'membership_auto' ) {
                $automatic_products = get_option( 'membership_automatic_renewal_products', array() );
                if ( ! in_array( $product_id, $automatic_products ) ) {
                    $automatic_products[] = $product_id;
                    update_option( 'membership_automatic_renewal_products', $automatic_products );
                    self::log( sprintf( __( 'Added product ID %d to automatic renewal products list', 'membership-manager' ), $product_id ) );
                }
            } else {
                $manual_products = get_option( 'membership_manual_renewal_products', array() );
                if ( ! in_array( $product_id, $manual_products ) ) {
                    $manual_products[] = $product_id;
                    update_option( 'membership_manual_renewal_products', $manual_products );
                    self::log( sprintf( __( 'Added product ID %d to manual renewal products list', 'membership-manager' ), $product_id ) );
                }
            }
            
            $results['converted']++;
            self::log( sprintf( __( 'Successfully converted product ID %d from %s to %s', 'membership-manager' ), $product_id, $current_type, $new_type ) );
        }
        
        self::log( sprintf( 
            __( 'Product migration summary: %d converted, %d already migrated, %d skipped', 'membership-manager' ),
            $results['converted'],
            $results['already_migrated'],
            $results['skipped']
        ) );
        
        return $results;
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
            $product = $item->get_product();
            
            // Check if it's a configured membership product
            if ( in_array( $product_id, $all_membership_products ) ) {
                $found_membership_product = true;
                if ( in_array( $product_id, $automatic_products ) ) {
                    $renewal_type = 'automatic';
                }
                break; 
            }
            
            // Auto-detect subscription products and set as automatic renewal
            if ( $product && class_exists( 'WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
                $found_membership_product = true;
                $renewal_type = 'automatic'; // Subscription products are always automatic
                self::log( sprintf( __( 'Detected subscription product (ID: %d) in order %d - setting as automatic renewal.', 'membership-manager' ), $product_id, $order_id ) );
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
            
            // Generate unique renewal token
            $renewal_token = self::generate_renewal_token();

            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'start_date' => $start_date->format( 'Y-m-d H:i:s' ),
                    'end_date' => $end_date->format( 'Y-m-d H:i:s' ),
                    'status' => 'active',
                    'renewal_type' => $renewal_type,
                    'renewal_token' => $renewal_token,
                    'status_changed_date' => current_time( 'mysql' ),
                )
            );
            
            $subscription_id = $wpdb->insert_id;
            
            self::log( sprintf( __( 'Created new membership for user ID: %d', 'membership-manager' ), $user_id ) );
            
            // Trigger activation hook
            do_action( 'membership_manager_subscription_activated', $user_id, $subscription_id );
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
            return '<span style="color: #00a32a; font-weight: 600;">' . __( 'Uden udløb', 'membership-manager' ) . '</span>';
        }
        
        // If on-hold, show when it was paused
        if ( $membership->status === 'on-hold' && ! empty( $membership->paused_date ) ) {
            return '<span style="color: #826eb4;">' . __( 'Pauseret: ', 'membership-manager' ) . esc_html( self::format_date_safely( $membership->paused_date ) ) . '</span>';
        }
        
        // For all statuses, show the end date if valid
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
        error_log( 'Membership Manager: Cleanup handler called.' );
        self::log( 'Cleanup handler called.' );
        
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( 'Membership Manager: Cleanup failed - insufficient permissions' );
            self::log( 'Cleanup failed: insufficient permissions', 'ERROR' );
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }
        
        error_log( 'Membership Manager: User permissions verified.' );
        self::log( 'User permissions verified.' );

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cleanup_invalid_dates_nonce' ) ) {
            error_log( 'Membership Manager: Cleanup failed - nonce verification failed' );
            self::log( 'Cleanup failed: nonce verification failed', 'ERROR' );
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }
        
        error_log( 'Membership Manager: Nonce verified, starting cleanup.' );
        self::log( 'Nonce verified, starting cleanup.' );

        // Perform cleanup with error handling
        try {
            $result = self::cleanup_invalid_dates();
            error_log( 'Membership Manager: Cleanup completed with result: ' . ( $result ? 'success' : 'failed' ) );
            self::log( sprintf( 'Cleanup completed with result: %s', $result ? 'success' : 'failed' ) );
        } catch ( \Exception $e ) {
            error_log( 'Membership Manager: Cleanup handler caught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
            self::log( sprintf( 'Cleanup handler caught exception: %s', $e->getMessage() ), 'ERROR' );
            $result = false;
        } catch ( \Error $e ) {
            error_log( 'Membership Manager: Cleanup handler caught error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
            self::log( sprintf( 'Cleanup handler caught error: %s', $e->getMessage() ), 'ERROR' );
            $result = false;
        }
        
        // Redirect with success/error message
        $redirect_url = add_query_arg( 
            array( 
                'page' => 'membership-migration',
                'cleanup' => $result ? 'success' : 'error'
            ),
            admin_url( 'admin.php' )
        );
        
        error_log( 'Membership Manager: Redirecting to: ' . $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    }

    public static function cleanup_invalid_dates() {
        error_log( 'Membership Manager: Starting cleanup of invalid end dates.' );
        self::log( 'Starting cleanup of invalid end dates.' );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        try {
            // Find memberships with invalid end dates - compatible with MySQL strict mode
            $query = "SELECT * FROM $table_name 
                     WHERE end_date IS NULL 
                        OR end_date < '1970-01-01'
                        OR YEAR(end_date) < 1970";
            
            error_log( 'Membership Manager: Executing query: ' . $query );
            self::log( 'Executing query: ' . $query );
            $invalid_memberships = $wpdb->get_results( $query );
            
            if ( $wpdb->last_error ) {
                error_log( 'Membership Manager: SQL Error: ' . $wpdb->last_error );
                self::log( 'SQL Error: ' . $wpdb->last_error, 'ERROR' );
                return false;
            }
            
            $count = is_array( $invalid_memberships ) ? count( $invalid_memberships ) : 0;
            error_log( 'Membership Manager: Found ' . $count . ' memberships with invalid dates.' );
            self::log( 'Found ' . $count . ' memberships with invalid dates.' );

            if ( $count === 0 ) {
                error_log( 'Membership Manager: No invalid dates to clean up.' );
                self::log( 'No invalid dates to clean up.' );
                return true;
            }

            $updated_count = 0;
            
            foreach ( $invalid_memberships as $membership ) {
                try {
                    // Validate membership object
                    if ( ! isset( $membership->id ) || ! isset( $membership->user_id ) ) {
                        self::log( 'Invalid membership object found, skipping.', 'WARNING' );
                        continue;
                    }
                    
                    // Generate a new end date based on start date + 1 year
                    if ( !empty( $membership->start_date ) && $membership->start_date !== '0000-00-00 00:00:00' ) {
                        try {
                            $start_datetime = new \DateTime( $membership->start_date );
                        } catch ( \Exception $e ) {
                            self::log( sprintf( 'Invalid start_date for membership ID %d: %s', $membership->id, $e->getMessage() ), 'WARNING' );
                            $start_datetime = new \DateTime();
                        }
                    } else {
                        $start_datetime = new \DateTime();
                    }
                        
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
                        error_log( sprintf( 'Membership Manager: Fixed end_date for membership ID %d (user %d): %s', 
                            $membership->id, $membership->user_id, $end_datetime->format( 'Y-m-d H:i:s' ) ) );
                        self::log( sprintf( 'Fixed end_date for membership ID %d (user %d): %s', 
                            $membership->id, $membership->user_id, $end_datetime->format( 'Y-m-d H:i:s' ) ) );
                    } else {
                        error_log( sprintf( 'Membership Manager: Failed to update membership ID %d (user %d): %s', 
                            $membership->id, $membership->user_id, $wpdb->last_error ) );
                        self::log( sprintf( 'Failed to update membership ID %d (user %d): %s', 
                            $membership->id, $membership->user_id, $wpdb->last_error ), 'ERROR' );
                    }
                } catch ( \Exception $e ) {
                    error_log( sprintf( 'Membership Manager: Exception processing membership ID %d: %s', 
                        isset($membership->id) ? $membership->id : 'unknown', $e->getMessage() ) );
                    self::log( sprintf( 'Exception processing membership ID %d: %s', 
                        isset($membership->id) ? $membership->id : 'unknown', $e->getMessage() ), 'ERROR' );
                    continue;
                }
            }

            error_log( sprintf( 'Membership Manager: Finished cleanup. Updated %d out of %d memberships with invalid end dates.', $updated_count, $count ) );
            self::log( sprintf( 'Finished cleanup. Updated %d out of %d memberships with invalid end dates.', $updated_count, $count ) );
            return true;
            
        } catch ( \Exception $e ) {
            error_log( sprintf( 'Membership Manager: Cleanup failed with exception: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
            self::log( sprintf( 'Cleanup failed with exception: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ), 'ERROR' );
            return false;
        } catch ( \Error $e ) {
            self::log( sprintf( 'Cleanup failed with fatal error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ), 'ERROR' );
            return false;
        }
    }

    /**
     * Handle update membership details with improved validation
     */
    public static function handle_update_membership_details() {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update_membership_details_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;
        if ( ! $membership_id ) {
            wp_die( __( 'Invalid membership ID.', 'membership-manager' ) );
        }

        // Sanitize input
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $renewal_type = isset( $_POST['renewal_type'] ) ? sanitize_text_field( $_POST['renewal_type'] ) : '';

        // Validate dates
        $start_date_validated = Membership_Utils::sanitize_date( $start_date );
        $end_date_validated = Membership_Utils::sanitize_date( $end_date );

        if ( $start_date_validated === false || $end_date_validated === false ) {
            wp_die( __( 'Invalid date format. Please use a valid date.', 'membership-manager' ) );
        }

        // Validate status and renewal type
        if ( ! Membership_Constants::is_valid_status( $status ) ) {
            wp_die( __( 'Invalid status value.', 'membership-manager' ) );
        }

        if ( ! Membership_Constants::is_valid_renewal_type( $renewal_type ) ) {
            wp_die( __( 'Invalid renewal type value.', 'membership-manager' ) );
        }

        global $wpdb;
        $table_name = Membership_Utils::get_table_name();
        
        // Get current membership to check if status changed
        $current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $membership_id ) );
        
        if ( ! $current ) {
            wp_die( __( 'Membership not found.', 'membership-manager' ) );
        }
        
        $old_status = $current->status;
        
        $update_data = array(
            'start_date' => $start_date_validated,
            'end_date' => $end_date_validated,
            'status' => $status,
            'renewal_type' => $renewal_type
        );
        
        // If status changed, update status_changed_date
        if ( $old_status !== $status ) {
            $update_data['status_changed_date'] = current_time( 'mysql' );
            
            // If changing to on-hold, set paused_date
            if ( $status === Membership_Constants::STATUS_ON_HOLD && $old_status !== Membership_Constants::STATUS_ON_HOLD ) {
                $update_data['paused_date'] = current_time( 'mysql' );
            }
            // If changing from on-hold to another status, clear paused_date
            elseif ( $old_status === Membership_Constants::STATUS_ON_HOLD && $status !== Membership_Constants::STATUS_ON_HOLD ) {
                $update_data['paused_date'] = NULL;
            }
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'id' => $membership_id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            self::log( sprintf( __( 'Database error updating membership ID %d: %s', 'membership-manager' ), $membership_id, $wpdb->last_error ), 'ERROR' );
            wp_die( __( 'Database error occurred. Please check the logs.', 'membership-manager' ) );
        }

        // Clear cache
        Membership_Utils::clear_membership_cache( $membership_id );

        self::log( sprintf( __( 'Updated membership ID: %d by user ID: %d', 'membership-manager' ), $membership_id, get_current_user_id() ) );
        
        // Trigger status change hook if status changed
        if ( $old_status !== $status ) {
            do_action( Membership_Constants::HOOK_STATUS_CHANGED, $membership_id, $old_status, $status );
        }

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

    /**
     * Handle add new membership with improved validation
     */
    public static function handle_add_new_membership() {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'add_new_membership_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        // Sanitize and validate input
        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active';
        $renewal_type = isset( $_POST['renewal_type'] ) ? sanitize_text_field( $_POST['renewal_type'] ) : 'manual';

        // Sanitize and validate dates first
        $start_date_validated = Membership_Utils::sanitize_date( $start_date );
        $end_date_validated = Membership_Utils::sanitize_date( $end_date );
        
        // If date sanitization fails, reject the request
        if ( false === $start_date_validated || false === $end_date_validated ) {
            wp_die( __( 'Invalid date format provided. Please use a valid date format.', 'membership-manager' ) );
        }

        // Validate data using utility class with validated dates
        $validation = Membership_Utils::validate_subscription_data( array(
            'user_id' => $user_id,
            'start_date' => $start_date_validated,
            'end_date' => $end_date_validated,
            'status' => $status,
            'renewal_type' => $renewal_type,
        ) );

        if ( ! $validation['valid'] ) {
            $errors = implode( '<br>', $validation['errors'] );
            wp_die( sprintf( __( 'Validation errors:<br>%s', 'membership-manager' ), $errors ) );
        }

        global $wpdb;
        $table_name = Membership_Utils::get_table_name();

        // Check if user already has a membership
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );
        if ( $existing ) {
            wp_die( sprintf( __( 'User ID %d already has a membership (ID: %d). Please edit the existing membership instead.', 'membership-manager' ), $user_id, $existing->id ) );
        }

        $renewal_token = Membership_Utils::generate_token();

        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status,
                'renewal_type' => $renewal_type,
                'renewal_token' => $renewal_token,
                'status_changed_date' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result === false ) {
            self::log( sprintf( __( 'Database error creating membership: %s', 'membership-manager' ), $wpdb->last_error ), 'ERROR' );
            wp_die( __( 'Database error occurred. Please check the logs.', 'membership-manager' ) );
        }

        $membership_id = $wpdb->insert_id;

        self::log( sprintf( __( 'Created new membership ID: %d for user ID: %d by admin.', 'membership-manager' ), $membership_id, $user_id ) );

        // Trigger activation if status is active
        if ( $status === Membership_Constants::STATUS_ACTIVE ) {
            do_action( Membership_Constants::HOOK_SUBSCRIPTION_ACTIVATED, $user_id, $membership_id );
        }

        wp_redirect( admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership_id . '&created=true' ) );
        exit;
    }

    public static function handle_generate_renewal_tokens() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'generate_renewal_tokens_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        // Find memberships without tokens
        $memberships = $wpdb->get_results( 
            "SELECT * FROM $table_name WHERE renewal_token IS NULL OR renewal_token = ''"
        );

        $updated_count = 0;

        foreach ( $memberships as $membership ) {
            $token = self::generate_renewal_token();
            
            $result = $wpdb->update(
                $table_name,
                array( 'renewal_token' => $token ),
                array( 'id' => $membership->id )
            );

            if ( $result !== false ) {
                $updated_count++;
            }
        }

        self::log( sprintf( __( 'Generated renewal tokens for %d memberships.', 'membership-manager' ), $updated_count ) );

        $redirect_url = add_query_arg(
            array(
                'page' => 'membership-migration',
                'tokens_generated' => $updated_count
            ),
            admin_url( 'admin.php' )
        );

        wp_redirect( $redirect_url );
        exit;
    }
    
    public static function handle_pause_membership() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pause_membership_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $membership_id = isset( $_GET['membership_id'] ) ? absint( $_GET['membership_id'] ) : 0;
        if ( ! $membership_id ) {
            wp_die( __( 'Invalid membership ID.', 'membership-manager' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Get current status
        $current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $membership_id ) );
        
        if ( ! $current ) {
            wp_die( __( 'Membership not found.', 'membership-manager' ) );
        }
        
        $old_status = $current->status;

        $wpdb->update(
            $table_name,
            array( 
                'status' => 'on-hold',
                'paused_date' => current_time( 'mysql' ),
                'status_changed_date' => current_time( 'mysql' )
            ),
            array( 'id' => $membership_id )
        );

        self::log( sprintf( __( 'Paused membership ID: %d by user ID: %d', 'membership-manager' ), $membership_id, get_current_user_id() ) );
        
        // Trigger status change hook
        do_action( 'membership_manager_status_changed', $membership_id, $old_status, 'on-hold' );

        wp_redirect( admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership_id . '&paused=true' ) );
        exit;
    }
    
    public static function handle_resume_membership() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'resume_membership_nonce' ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        $membership_id = isset( $_GET['membership_id'] ) ? absint( $_GET['membership_id'] ) : 0;
        if ( ! $membership_id ) {
            wp_die( __( 'Invalid membership ID.', 'membership-manager' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Get current status
        $current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $membership_id ) );
        
        if ( ! $current ) {
            wp_die( __( 'Membership not found.', 'membership-manager' ) );
        }
        
        $old_status = $current->status;

        $wpdb->update(
            $table_name,
            array( 
                'status' => 'active',
                'paused_date' => NULL,
                'status_changed_date' => current_time( 'mysql' )
            ),
            array( 'id' => $membership_id )
        );

        self::log( sprintf( __( 'Resumed membership ID: %d by user ID: %d', 'membership-manager' ), $membership_id, get_current_user_id() ) );
        
        // Trigger status change hook (will also trigger activation if coming from on-hold)
        do_action( 'membership_manager_status_changed', $membership_id, $old_status, 'active' );

        wp_redirect( admin_url( 'admin.php?page=membership-manager&action=view&id=' . $membership_id . '&resumed=true' ) );
        exit;
    }

    /**
     * Log messages to file with proper error handling
     * 
     * @param string $message The message to log
     * @param string $type The log type (INFO, WARNING, ERROR)
     * @return bool True if logged successfully, false otherwise
     */
    public static function log( $message, $type = 'INFO' ) {
        $log_file = trailingslashit( MEMBERSHIP_MANAGER_PLUGIN_DIR ) . 'logs/membership.log';
        $log_dir = dirname( $log_file );
        
        // Ensure log directory exists
        if ( ! file_exists( $log_dir ) ) {
            if ( ! wp_mkdir_p( $log_dir ) ) {
                error_log( 'Membership Manager: Failed to create log directory' );
                return false;
            }
        }
        
        // Check if log file is writable
        if ( file_exists( $log_file ) && ! is_writable( $log_file ) ) {
            error_log( 'Membership Manager: Log file is not writable' );
            return false;
        }
        
        // Rotate log if it gets too large (5MB)
        if ( file_exists( $log_file ) && filesize( $log_file ) > 5 * 1024 * 1024 ) {
            $backup_file = $log_file . '.' . date( 'Y-m-d-His' ) . '.bak';
            rename( $log_file, $backup_file );
            
            // Keep only last 5 backup files
            $backups = glob( $log_dir . '/membership.log.*.bak' );
            if ( count( $backups ) > 5 ) {
                array_multisort( array_map( 'filemtime', $backups ), SORT_ASC, $backups );
                foreach ( array_slice( $backups, 0, count( $backups ) - 5 ) as $old_backup ) {
                    unlink( $old_backup );
                }
            }
        }
        
        $timestamp = date( 'Y-m-d H:i:s' );
        $log_message = "[$timestamp] [$type] - $message" . PHP_EOL;
        
        return file_put_contents( $log_file, $log_message, FILE_APPEND ) !== false;
    }
    
    /**
     * Generate a unique renewal token
     * 
     * @return string Unique token
     */
    public static function generate_renewal_token() {
        return bin2hex( random_bytes( 32 ) );
    }
    
    /**
     * Get renewal link for a subscription
     * 
     * @param object $subscription The subscription object
     * @return string The renewal URL
     */
    public static function get_renewal_link( $subscription ) {
        // Ensure token exists
        if ( empty( $subscription->renewal_token ) ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'membership_subscriptions';
            $token = self::generate_renewal_token();
            
            $wpdb->update(
                $table_name,
                array( 'renewal_token' => $token ),
                array( 'id' => $subscription->id )
            );
            
            $subscription->renewal_token = $token;
        }
        
        return home_url( '/membership-renewal/' . $subscription->renewal_token . '/' );
    }
    
    /**
     * Register renewal endpoint
     */
    public static function register_renewal_endpoint() {
        add_rewrite_rule( '^membership-renewal/([a-f0-9]+)/?$', 'index.php?membership_renewal_token=$matches[1]', 'top' );
        add_rewrite_tag( '%membership_renewal_token%', '([a-f0-9]+)' );
    }
    
    /**
     * Handle renewal token when accessed
     */
    public static function handle_renewal_token() {
        $token = get_query_var( 'membership_renewal_token' );
        
        if ( empty( $token ) ) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Find subscription by token
        $subscription = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE renewal_token = %s",
            $token
        ) );
        
        if ( ! $subscription ) {
            wp_die( __( 'Invalid renewal link. Please contact support.', 'membership-manager' ) );
        }
        
        // Get manual renewal product
        $manual_products = get_option( 'membership_manual_renewal_products', array() );
        
        if ( empty( $manual_products ) ) {
            wp_die( __( 'No renewal product configured. Please contact support.', 'membership-manager' ) );
        }
        
        $product_id = $manual_products[0];
        
        // Clear cart
        WC()->cart->empty_cart();
        
        // Add product to cart
        WC()->cart->add_to_cart( $product_id, 1 );
        
        // Add subscription ID to cart for reference
        WC()->session->set( 'renewing_membership_id', $subscription->id );
        
        self::log( sprintf( __( 'User accessed renewal link for subscription ID: %d, redirecting to checkout', 'membership-manager' ), $subscription->id ) );
        
        // Redirect to checkout
        wp_redirect( wc_get_checkout_url() );
        exit;
    }
    
    /**
     * Regenerate renewal token for a subscription
     * 
     * @param int $subscription_id The subscription ID
     * @return string|false New token or false on failure
     */
    public static function regenerate_renewal_token( $subscription_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        $token = self::generate_renewal_token();
        
        $result = $wpdb->update(
            $table_name,
            array( 'renewal_token' => $token ),
            array( 'id' => $subscription_id )
        );
        
        if ( $result !== false ) {
            self::log( sprintf( __( 'Regenerated renewal token for subscription ID: %d', 'membership-manager' ), $subscription_id ) );
            return $token;
        }
        
        return false;
    }
    
    /**
     * Handle validation request from admin interface
     */
    public static function handle_validate_membership_data() {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-manager' ) );
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'validate_membership_data_nonce' ) ) {
            self::log( __( 'Nonce verification failed for validation.', 'membership-manager' ), 'ERROR' );
            wp_die( __( 'Security check failed. Please try again.', 'membership-manager' ) );
        }

        // Perform validation
        $validation_results = self::validate_membership_data();
        
        // Store results in transient for display
        set_transient( 'membership_validation_results', $validation_results, 300 ); // 5 minutes
        
        // Redirect with success message
        $redirect_url = add_query_arg( 
            array( 
                'page' => 'membership-migration',
                'validation' => 'completed'
            ),
            admin_url( 'admin.php' )
        );
        
        wp_redirect( $redirect_url );
        exit;
    }
    
    /**
     * Validate membership data against WooCommerce orders
     * 
     * This method checks that membership numbers are correct in relation to WooCommerce orders.
     * It verifies:
     * - All completed orders with membership products have corresponding memberships
     * - Memberships have valid associated orders
     * - Data consistency between orders and memberships
     * 
     * @return array Validation results with statistics and discrepancies
     */
    public static function validate_membership_data() {
        self::log( __( 'Starting membership data validation.', 'membership-manager' ) );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';
        
        // Initialize results
        $results = array(
            'total_orders_checked' => 0,
            'total_memberships_checked' => 0,
            'orders_with_membership' => 0,
            'orders_without_membership' => 0,
            'memberships_with_order' => 0,
            'orphaned_memberships' => 0,
            'data_mismatches' => 0,
            'issues' => array(),
            'success' => true,
        );
        
        // Get membership product IDs
        $automatic_products = get_option( 'membership_automatic_renewal_products', array() );
        $manual_products = get_option( 'membership_manual_renewal_products', array() );
        $all_membership_products = array_merge( $automatic_products, $manual_products );
        
        if ( empty( $all_membership_products ) ) {
            $results['success'] = false;
            $results['issues'][] = array(
                'type' => 'error',
                'message' => __( 'No membership products configured. Please configure membership products in settings first.', 'membership-manager' )
            );
            self::log( __( 'Validation failed: No membership products configured.', 'membership-manager' ), 'WARNING' );
            return $results;
        }
        
        try {
            // Part 1: Check completed orders with membership products
            // Also build a map of users with membership orders for Part 2
            $orders = wc_get_orders( array(
                'limit' => -1,
                'status' => array( 'completed', 'processing' ),
                'return' => 'ids',
            ) );
            
            $results['total_orders_checked'] = count( $orders );
            
            // Map to track which users have orders with membership products
            $users_with_orders = array();
            
            foreach ( $orders as $order_id ) {
                $order = wc_get_order( $order_id );
                
                if ( ! $order ) {
                    continue;
                }
                
                // Check if order contains membership products
                $has_membership_product = false;
                $expected_renewal_type = 'manual';
                
                foreach ( $order->get_items() as $item ) {
                    $product_id = $item->get_product_id();
                    $product = $item->get_product();
                    
                    if ( in_array( $product_id, $all_membership_products ) ) {
                        $has_membership_product = true;
                        if ( in_array( $product_id, $automatic_products ) ) {
                            $expected_renewal_type = 'automatic';
                        }
                        break;
                    }
                    
                    // Also check for subscription products
                    if ( $product && class_exists( 'WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
                        $has_membership_product = true;
                        $expected_renewal_type = 'automatic';
                        break;
                    }
                }
                
                if ( ! $has_membership_product ) {
                    continue;
                }
                
                // Order has membership product - check if membership exists
                $user_id = $order->get_user_id();
                
                if ( ! $user_id ) {
                    $results['orders_without_membership']++;
                    $results['issues'][] = array(
                        'type' => 'warning',
                        'order_id' => $order_id,
                        'message' => sprintf( __( 'Order #%d has membership product but no user ID (guest order).', 'membership-manager' ), $order_id )
                    );
                    continue;
                }
                
                // Mark user as having an order with membership products (for Part 2)
                $users_with_orders[ $user_id ] = true;
                
                // Check if membership was marked as created for this order
                $membership_created = get_post_meta( $order_id, '_membership_created', true );
                $membership_ids = get_post_meta( $order_id, '_membership_ids', true );
                
                if ( $membership_created && ! empty( $membership_ids ) ) {
                    $results['orders_with_membership']++;
                    
                    // Verify the membership still exists in database
                    foreach ( (array) $membership_ids as $membership_id ) {
                        $membership = self::get_membership( $membership_id );
                        
                        if ( ! $membership ) {
                            $results['issues'][] = array(
                                'type' => 'error',
                                'order_id' => $order_id,
                                'membership_id' => $membership_id,
                                'message' => sprintf( __( 'Order #%d references membership #%d which no longer exists in database.', 'membership-manager' ), $order_id, $membership_id )
                            );
                            $results['data_mismatches']++;
                        } else {
                            // Verify user_id matches
                            if ( $membership->user_id !== $user_id ) {
                                $results['issues'][] = array(
                                    'type' => 'error',
                                    'order_id' => $order_id,
                                    'membership_id' => $membership_id,
                                    'message' => sprintf( __( 'Order #%d (user %d) has membership #%d but membership belongs to user %d.', 'membership-manager' ), $order_id, $user_id, $membership_id, $membership->user_id )
                                );
                                $results['data_mismatches']++;
                            }
                        }
                    }
                } else {
                    // Order should have membership but doesn't
                    $results['orders_without_membership']++;
                    
                    // Check if user has ANY membership (might be created but not linked)
                    $user_membership = self::get_user_membership( $user_id );
                    
                    if ( $user_membership ) {
                        $results['issues'][] = array(
                            'type' => 'warning',
                            'order_id' => $order_id,
                            'user_id' => $user_id,
                            'membership_id' => $user_membership->id,
                            'message' => sprintf( __( 'Order #%d (user %d) should have membership but meta is not set. User has membership #%d.', 'membership-manager' ), $order_id, $user_id, $user_membership->id )
                        );
                    } else {
                        $results['issues'][] = array(
                            'type' => 'error',
                            'order_id' => $order_id,
                            'user_id' => $user_id,
                            'message' => sprintf( __( 'Order #%d (user %d) should have membership but none exists for this user.', 'membership-manager' ), $order_id, $user_id )
                        );
                    }
                }
            }
            
            // Part 2: Check all memberships to see if they have valid orders
            // Use the user map built in Part 1 to avoid re-querying orders
            self::log( __( 'Checking memberships against order map...', 'membership-manager' ) );
            
            // Now fetch memberships and check against the order map
            $all_memberships = $wpdb->get_results( "SELECT id, user_id FROM $table_name" );
            $results['total_memberships_checked'] = count( $all_memberships );
            
            foreach ( $all_memberships as $membership ) {
                $user_id = $membership->user_id;
                
                // Check if user has any order with membership products
                if ( isset( $users_with_orders[ $user_id ] ) && $users_with_orders[ $user_id ] ) {
                    $results['memberships_with_order']++;
                } else {
                    $results['orphaned_memberships']++;
                    $results['issues'][] = array(
                        'type' => 'info',
                        'membership_id' => $membership->id,
                        'user_id' => $user_id,
                        'message' => sprintf( __( 'Membership #%d (user %d) has no associated completed order with membership products. May be manually created or migrated.', 'membership-manager' ), $membership->id, $user_id )
                    );
                }
            }
            
            self::log( sprintf( 
                __( 'Validation completed: %d orders checked, %d memberships checked, %d issues found.', 'membership-manager' ),
                $results['total_orders_checked'],
                $results['total_memberships_checked'],
                count( $results['issues'] )
            ) );
            
        } catch ( Exception $e ) {
            $results['success'] = false;
            $results['issues'][] = array(
                'type' => 'error',
                'message' => sprintf( __( 'Validation failed with error: %s', 'membership-manager' ), $e->getMessage() )
            );
            self::log( sprintf( __( 'Validation failed with error: %s', 'membership-manager' ), $e->getMessage() ), 'ERROR' );
        }
        
        return $results;
    }
}
