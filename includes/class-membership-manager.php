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
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'migrate_subscriptions' ) {
            self::migrate_woocommerce_subscription();
        }
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
    }

    public static function render_admin_page() {
        include_once plugin_dir_path( __FILE__ ) . '../admin/views/memberships-list.php';
    }


    public static function render_migration_page() {
        include_once plugin_dir_path( __FILE__ ) . '../admin/views/migration-interface.php';
    }

    public static function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_membership-manager' !== $hook && 'memberships_page_membership-migration' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'membership-admin', plugin_dir_url( __FILE__ ) . '../admin/js/admin.js', array( 'jquery' ), '1.0.0', true );
    }

    public static function filter_memberships() {
        // ... (omitted for brevity)
    }

    public static function migrate_woocommerce_subscription() {
        self::log( __( 'Starting WooCommerce subscription migration.', 'membership-manager' ) );
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'migrate_subscriptions_nonce' ) ) {
            self::log( __( 'Nonce verification failed for migration.', 'membership-manager' ), 'ERROR' );
            return;
        }

        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            self::log( __( 'WooCommerce Subscriptions not active for migration.', 'membership-manager' ), 'ERROR' );
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __( 'WooCommerce Subscriptions is not active.', 'membership-manager' ) . '</p></div>';
            });
            return;
        }

        $subscriptions = wcs_get_subscriptions( array( 'subscriptions_per_page' => -1 ) );

        global $wpdb;
        $table_name = $wpdb->prefix . 'membership_subscriptions';

        foreach ( $subscriptions as $subscription ) {
            $user_id = $subscription->get_user_id();
            $start_date = $subscription->get_date( 'start_date' );
            $end_date = $subscription->get_date( 'end_date' );
            $status = $subscription->get_status();

            // Check if subscription already exists
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );

            if ( ! $existing ) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'status' => $status,
                        'renewal_type' => 'automatic', // Assuming WC subscriptions are automatic
                    )
                );
                self::log( sprintf( __( 'Migrated subscription for user ID: %d', 'membership-manager' ), $user_id ) );
            } else {
                self::log( sprintf( __( 'Subscription already exists for user ID: %d. Skipping.', 'membership-manager' ), $user_id ) );
            }
        }

        self::log( __( 'Finished WooCommerce subscription migration.', 'membership-manager' ) );
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __( 'Migration completed.', 'membership-manager' ) . '</p></div>';
        });
    }


    public static function create_membership_subscription( $order_id ) {
        self::log( sprintf( __( 'Creating or extending membership for order ID: %d', 'membership-manager' ), $order_id ) );
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();

        if ( ! $user_id ) {
            self::log( sprintf( __( 'No user ID found for order ID: %d. Aborting.', 'membership-manager' ), $order_id ), 'WARNING' );
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
            $end_date->modify( '+1 year' );
            $wpdb->update(
                $table_name,
                array( 'end_date' => $end_date->format( 'Y-m-d H:i:s' ) ),
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
                    'renewal_type' => 'manual', // a
                )
            );
            self::log( sprintf( __( 'Created new membership for user ID: %d', 'membership-manager' ), $user_id ) );
        }
    }

    public static function log( $message, $type = 'INFO' ) {
        $log_file = plugin_dir_path( __FILE__ ) . '../logs/membership.log';
        $timestamp = date( 'Y-m-d H:i:s' );
        $log_message = "[$timestamp] [$type] - $message" . PHP_EOL;
        file_put_contents( $log_file, $log_message, FILE_APPEND );
    }
}
