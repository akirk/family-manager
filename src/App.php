<?php

namespace FamilyManager;

use WpApp\WpApp;
use WpApp\BaseApp;

class App extends BaseApp {
    public function __construct() {
        // See https://github.com/akirk/wp-app for documentation.
        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            'require_login'       => true,
            'show_wp_logo'        => true,
            'show_site_name'      => true,
            'app_name'            => $this->get_plugin_name(),
            'app_name_textdomain' => 'family-manager',
            'launcher'            => true,
            // Owned content: REST reads are gated with the app's capability and
            // OpenStation keeps these menus out of its dock.
            'post_types'          => [ 'family_household', 'family_task', 'family_reward' ],
            'taxonomies'          => [ 'family_member' ],
        ] );

        $this->storage = new Storage();

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'wp_ajax_family_manager_dashboard', [ $this, 'handle_dashboard_request' ] );
    }

    protected function get_url_path(): string {
        return 'family-manager';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    protected function get_plugin_name(): string {
        if ( ! function_exists( 'get_file_data' ) ) {
            return 'Family Manager';
        }

        $plugin_data = get_file_data( dirname( __DIR__ ) . '/family-manager.php', [ 'name' => 'Plugin Name' ] );

        return $plugin_data['name'] ?: 'Family Manager';
    }

    protected function setup_storage(): void {
        if ( ! $this->storage ) {
            $this->storage = new Storage();
        }
    }

    protected function setup_database(): void {
        $this->setup_storage();
    }

    protected function setup_routes(): void {
        $this->app->route( '' );
    }

    protected function setup_menu(): void {
        $this->app->add_menu_item(
            'dashboard',
            __( 'Dashboard', 'family-manager' ),
            home_url( '/' . $this->get_url_path() . '/' )
        );
    }

    public function register_post_types(): void {
        $post_types = [
            'family_household' => [
                'singular' => __( 'Household', 'family-manager' ),
                'plural'   => __( 'Households', 'family-manager' ),
                'supports' => [ 'title', 'author' ],
            ],
            'family_task'      => [
                'singular' => __( 'Family Task', 'family-manager' ),
                'plural'   => __( 'Family Tasks', 'family-manager' ),
                'supports' => [ 'title', 'page-attributes' ],
            ],
            'family_reward'    => [
                'singular' => __( 'Family Reward', 'family-manager' ),
                'plural'   => __( 'Family Rewards', 'family-manager' ),
                'supports' => [ 'title', 'page-attributes' ],
            ],
        ];

        foreach ( $post_types as $post_type => $labels ) {
            register_post_type( $post_type, [
                'labels'              => [
                    'name'          => $labels['plural'],
                    'singular_name' => $labels['singular'],
                ],
                'public'              => false,
                'show_ui'             => current_user_can( 'manage_options' ),
                'show_in_menu'        => current_user_can( 'manage_options' ),
                'show_in_rest'        => true,
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
                'supports'            => $labels['supports'],
                'exclude_from_search' => true,
            ] );
        }

        register_taxonomy( 'family_member', [ 'family_task', 'family_reward' ], [
            'labels'            => [
                'name'          => __( 'Family Members', 'family-manager' ),
                'singular_name' => __( 'Family Member', 'family-manager' ),
            ],
            'public'            => false,
            'show_ui'           => current_user_can( 'manage_options' ),
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'show_admin_column' => true,
        ] );
    }

    public function handle_dashboard_request(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Please log in to manage your household.', 'family-manager' ) ], 401 );
        }

        check_ajax_referer( 'family_manager_app', 'nonce' );

        $user_id = get_current_user_id();
        $action = isset( $_POST['family_action'] ) ? sanitize_key( wp_unslash( $_POST['family_action'] ) ) : 'get';
        $dashboard = $this->storage->get_dashboard( $user_id );
        $household_id = (int) $dashboard['household']['id'];

        if ( ! $this->storage->user_can_access_household( $user_id, $household_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have access to this household.', 'family-manager' ) ], 403 );
        }

        if ( 'add_member' === $action ) {
            $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            if ( '' !== $name ) {
                $this->storage->add_member( $household_id, $name );
            }
        } elseif ( 'add_task' === $action ) {
            $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $member_id = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;
            $task_type = isset( $_POST['task_type'] ) ? sanitize_key( wp_unslash( $_POST['task_type'] ) ) : 'task';
            $points = isset( $_POST['points'] ) ? absint( $_POST['points'] ) : 0;
            $due_date = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';

            if ( '' !== $title ) {
                $this->storage->add_task( $household_id, $title, $member_id, $task_type, $points, $due_date );
            }
        } elseif ( 'toggle_task' === $action ) {
            $task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
            if ( $task_id ) {
                $this->storage->toggle_task( $household_id, $task_id );
            }
        } elseif ( 'add_reward' === $action ) {
            $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $member_id = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;
            $points = isset( $_POST['points'] ) ? absint( $_POST['points'] ) : 0;

            if ( '' !== $title ) {
                $this->storage->add_reward( $household_id, $title, $member_id, $points );
            }
        }

        wp_send_json_success( $this->storage->get_dashboard( $user_id ) );
    }

    public function activate(): void {
        $this->setup_storage();
        $this->register_post_types();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }
}
