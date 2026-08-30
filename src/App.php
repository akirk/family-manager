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

        // Members get a WordPress account with this role: enough to log in and
        // reach the app, nothing else.
        $this->app->add_role( 'member', __( 'Family Member', 'family-manager' ), [ 'read' => true ] );

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
        // A household administrator viewing the app as one of its members.
        $this->app->route( 'member/{id}', 'index.php' );
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
        $subject_id = isset( $_POST['view_as'] ) ? absint( $_POST['view_as'] ) : 0;
        $subject_id = $subject_id ?: $user_id;
        $action = isset( $_POST['family_action'] ) ? sanitize_key( wp_unslash( $_POST['family_action'] ) ) : 'get';

        if ( ! Access::can_view_user( $user_id, $subject_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have access to this member.', 'family-manager' ) ], 403 );
        }

        $dashboard = $this->storage->get_dashboard( $user_id, $subject_id );
        $household_id = isset( $dashboard['household']['id'] ) ? (int) $dashboard['household']['id'] : 0;
        if ( ! $household_id ) {
            wp_send_json_error( [ 'message' => __( 'No household found.', 'family-manager' ) ], 404 );
        }

        $can_manage = Access::can_manage( $user_id, $household_id );
        $can_organise = Access::can_organise( $user_id, $household_id );
        $post = static function( string $key, string $filter = 'text' ) {
            if ( ! isset( $_POST[ $key ] ) ) {
                return 'int' === $filter ? 0 : '';
            }
            $value = wp_unslash( $_POST[ $key ] );
            switch ( $filter ) {
                case 'int':
                    return absint( $value );
                case 'key':
                    return sanitize_key( $value );
                case 'email':
                    return sanitize_email( $value );
                case 'raw':
                    return (string) $value;
                default:
                    return sanitize_text_field( $value );
            }
        };

        switch ( $action ) {
            case 'add_member':
                $this->assert_allowed( $can_manage );
                $this->storage->add_member( $household_id, $post( 'name' ), $post( 'role', 'key' ) ?: Access::ROLE_CHILD, $post( 'email', 'email' ), $post( 'password', 'raw' ) );
                break;

            case 'set_member_role':
                $this->assert_allowed( $can_manage );
                $member_id = $post( 'member_id', 'int' );
                if ( $member_id && Access::is_member( $member_id, $household_id ) && $member_id !== $user_id ) {
                    Access::set_member_role( $household_id, $member_id, $post( 'role', 'key' ) );
                }
                break;

            case 'remove_member':
                $this->assert_allowed( $can_manage );
                $member_id = $post( 'member_id', 'int' );
                if ( $member_id && $member_id !== $user_id ) {
                    $this->storage->remove_member( $household_id, $member_id );
                }
                break;

            case 'add_task':
                $this->assert_allowed( $can_organise );
                if ( '' !== $post( 'title' ) ) {
                    $this->storage->add_task( $household_id, $post( 'title' ), $post( 'member_id', 'int' ), $post( 'task_type', 'key' ) ?: 'task', $post( 'points', 'int' ), $post( 'due_date' ) );
                }
                break;

            case 'toggle_task':
                $task_id = $post( 'task_id', 'int' );
                // Members may tick their own or household-wide tasks; organisers any task.
                $visible = array_column( $dashboard['tasks'], 'id' );
                if ( $task_id && ( $can_organise || in_array( $task_id, $visible, true ) ) ) {
                    $this->storage->toggle_task( $household_id, $task_id, $user_id );
                }
                break;

            case 'add_reward':
                $this->assert_allowed( $can_organise );
                if ( '' !== $post( 'title' ) ) {
                    $this->storage->add_reward( $household_id, $post( 'title' ), $post( 'member_id', 'int' ), $post( 'points', 'int' ) );
                }
                break;
        }

        wp_send_json_success( $this->storage->get_dashboard( $user_id, $subject_id ) );
    }

    private function assert_allowed( bool $allowed ): void {
        if ( ! $allowed ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to do that.', 'family-manager' ) ], 403 );
        }
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
