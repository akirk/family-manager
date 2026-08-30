<?php

namespace Households;

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
            'app_name_textdomain' => 'households',
            'launcher'            => true,
            // Owned content: REST reads are gated with the app's capability and
            // OpenStation keeps these menus out of its dock.
            'post_types'          => [ 'household', 'household_task' ],
            'taxonomies'          => [ 'household_member' ],
        ] );

        $this->storage = new Storage();

        // Members get a WordPress account with this role: enough to log in and
        // reach the app, nothing else.
        $this->app->add_role( 'member', __( 'Household Member', 'households' ), [ 'read' => true ] );

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'template_redirect', [ $this, 'route_by_household' ] );
        add_filter( 'login_redirect', [ $this, 'redirect_members_to_app' ], 10, 3 );
        add_action( 'wp_ajax_households_dashboard', [ $this, 'handle_dashboard_request' ] );
    }

    protected function get_url_path(): string {
        return 'households';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    protected function get_plugin_name(): string {
        if ( ! function_exists( 'get_file_data' ) ) {
            return 'Households';
        }

        $plugin_data = get_file_data( dirname( __DIR__ ) . '/households.php', [ 'name' => 'Plugin Name' ] );

        return $plugin_data['name'] ?: 'Households';
    }

    protected function setup_storage(): void {
        if ( ! $this->storage ) {
            $this->storage = new Storage();
        }
    }

    protected function setup_database(): void {
        $this->setup_storage();
    }

    /**
     * Every home has its own address.
     *
     * The literal routes are registered before `{id}`, and `{id}` only matches
     * digits, so a home can never shadow `where` or `profile`.
     */
    protected function setup_routes(): void {
        // The index: every home you belong to.
        $this->app->route( '' );
        // Who is at which home, day by day, across the homes you belong to.
        $this->app->route( 'where', 'where.php' );
        // A person, and the things about them that travel between homes. The
        // var is `user_id` so that `id` always means a home, wherever it appears.
        $this->app->route( 'profile/{user_id}', 'profile.php' );
        // One home: its tasks, appointments, members and notes.
        $this->app->route( '{id}', 'home.php' );
        // Managing that home: members, roles, settings.
        $this->app->route( '{id}/manage', 'manage.php' );
        // That home, seen as one of its members.
        $this->app->route( '{id}/as/{user_id}', 'home.php' );
    }

    protected function setup_menu(): void {
        $base = home_url( '/' . $this->get_url_path() . '/' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            $this->app->add_menu_item( 'index', __( 'Your homes', 'households' ), $base );
            return;
        }

        $households = $this->storage->get_households_for_user( $user_id );
        $this->app->add_menu_item( 'index', count( $households ) > 1 ? __( 'Your homes', 'households' ) : __( 'Home', 'households' ), $base );

        // Each home is a place you go to, not a mode you switch into.
        foreach ( $households as $household ) {
            $this->app->add_menu_item( 'household-' . $household['id'], $household['name'], $base . $household['id'] . '/' );
        }

        $this->app->add_menu_item( 'where', __( 'Who is where', 'households' ), $base . 'where/' );

        $open = $this->household_in_view( $user_id );
        if ( $open && Access::can_manage( $user_id, $open ) ) {
            $this->app->add_menu_item( 'manage', __( 'Manage this home', 'households' ), $base . $open . '/manage/' );
            foreach ( $this->storage->get_members( $open ) as $member ) {
                if ( $member['id'] === $user_id ) {
                    continue;
                }
                $this->app->add_menu_item(
                    'view-as-' . $member['id'],
                    sprintf( __( 'View as %s', 'households' ), $member['name'] ),
                    $base . $open . '/as/' . $member['id'] . '/'
                );
            }
        }

        $this->app->add_user_menu_item( 'my-profile', __( 'My profile', 'households' ), $base . 'profile/' . $user_id . '/' );
    }

    /**
     * The home the current page is about: the one in the URL, else the last one
     * visited. Used for the menu and for the views that span homes.
     */
    public function household_in_view( int $user_id ): int {
        $from_url = (int) get_query_var( 'id' );
        if ( $from_url && Access::is_member( $user_id, $from_url ) ) {
            return $from_url;
        }
        return $this->storage->last_household_id( $user_id );
    }

    /**
     * The path within the app for this request, or null if this is not one.
     *
     * The router itself only runs at `template_include`, by which point it is
     * too late to redirect; this reads the same path from the query vars
     * WordPress has already parsed.
     */
    private function app_request_path(): ?string {
        global $wp_query;
        if ( ! $wp_query || ! isset( $wp_query->query_vars['wp_app_request'] ) ) {
            return null;
        }
        if ( get_query_var( 'wp_app_path' ) !== $this->get_url_path() ) {
            return null;
        }
        return trim( (string) get_query_var( 'wp_app_request' ), '/' );
    }

    /**
     * Every home is addressed by ID, so both of these run before anything is
     * rendered: one sends a lone householder straight in, the other turns away
     * a request for a home the viewer does not belong to.
     */
    public function route_by_household(): void {
        $path = $this->app_request_path();
        if ( null === $path || ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $index = home_url( '/' . $this->get_url_path() . '/' );

        if ( '' === $path ) {
            $households = Access::household_ids_for_user( $user_id );
            if ( 1 === count( $households ) ) {
                wp_safe_redirect( $index . $households[0] . '/' );
                exit;
            }
            return;
        }

        if ( preg_match( '#^(\d+)(?:/|$)#', $path, $matches ) && ! Access::is_member( $user_id, (int) $matches[1] ) ) {
            wp_safe_redirect( $index );
            exit;
        }
    }

    /** Members have nothing to do in wp-admin; send them to the app after login. */
    public function redirect_members_to_app( $redirect_to, $requested, $user ) {
        if ( $user instanceof \WP_User && in_array( Storage::WP_ROLE, (array) $user->roles, true ) ) {
            return home_url( '/' . $this->get_url_path() . '/' );
        }
        return $redirect_to;
    }

    public function register_post_types(): void {
        $post_types = [
            'household'      => [
                'singular' => __( 'Household', 'households' ),
                'plural'   => __( 'Households', 'households' ),
                'supports' => [ 'title', 'author' ],
            ],
            'household_task' => [
                'singular' => __( 'Household Task', 'households' ),
                'plural'   => __( 'Household Tasks', 'households' ),
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

        register_taxonomy( 'household_member', [ 'household_task' ], [
            'labels'            => [
                'name'          => __( 'Household Members', 'households' ),
                'singular_name' => __( 'Household Member', 'households' ),
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
            wp_send_json_error( [ 'message' => __( 'Please log in to manage your household.', 'households' ) ], 401 );
        }
        check_ajax_referer( 'households_app', 'nonce' );

        $user_id = get_current_user_id();
        $subject_id = isset( $_POST['view_as'] ) ? absint( $_POST['view_as'] ) : 0;
        $subject_id = $subject_id ?: $user_id;
        $action = isset( $_POST['household_action'] ) ? sanitize_key( wp_unslash( $_POST['household_action'] ) ) : 'get';

        if ( ! Access::can_view_user( $user_id, $subject_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have access to this member.', 'households' ) ], 403 );
        }

        if ( 'get_households' === $action ) {
            wp_send_json_success( [ 'households' => $this->storage->get_households_overview( $user_id ) ] );
        }

        // The home is named by the page asking. A request that names one the
        // viewer does not belong to is refused rather than quietly answered
        // about a different home; only a request that names none at all — the
        // views that span homes — falls back to the last one visited.
        if ( isset( $_POST['household_id'] ) && absint( $_POST['household_id'] ) ) {
            $household_id = absint( $_POST['household_id'] );
            $this->assert_allowed( Access::is_member( $user_id, $household_id ) );
        } else {
            $household_id = $this->storage->last_household_id( $user_id );
        }
        if ( ! $household_id ) {
            wp_send_json_error( [ 'message' => __( 'No household found.', 'households' ) ], 404 );
        }
        $this->storage->remember_household( $user_id, $household_id );

        $dashboard = $this->storage->get_dashboard( $user_id, $household_id, $subject_id );
        if ( empty( $dashboard['household']['id'] ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have access to this home.', 'households' ) ], 403 );
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

        // Profile actions address a member directly rather than a "view as" subject.
        if ( 'get_profile' === $action || 'save_profile' === $action ) {
            $member_id = $post( 'member_id', 'int' ) ?: $user_id;
            $this->assert_allowed( Access::is_member( $member_id, $household_id ) && ( $member_id === $user_id || Access::can_view_user( $user_id, $member_id ) || $can_organise ) );
            if ( 'save_profile' === $action ) {
                $fields = [];
                foreach ( array_keys( Storage::PROFILE_FIELDS ) as $field ) {
                    $fields[ $field ] = $post( $field, 'raw' );
                }
                $this->storage->save_profile( $member_id, $fields );
            }
            wp_send_json_success( [
                'profile'     => $this->storage->get_profile( $member_id, $household_id ),
                'household'   => $dashboard['household'],
                'permissions' => [ 'edit' => true ],
            ] );
        }

        // Whereabouts actions answer with the board rather than the dashboard.
        if ( in_array( $action, [ 'get_whereabouts', 'save_rotation', 'clear_rotation', 'set_override' ], true ) ) {
            if ( 'get_whereabouts' !== $action ) {
                $this->assert_allowed( $can_organise );
                $member_id = $post( 'member_id', 'int' );
                $this->assert_allowed( $member_id && Access::is_member( $member_id, $household_id ) );

                if ( 'save_rotation' === $action ) {
                    $homes = array_map( 'absint', (array) ( isset( $_POST['homes'] ) ? wp_unslash( $_POST['homes'] ) : [] ) );
                    $cycle = array_map( 'absint', (array) ( isset( $_POST['cycle'] ) ? wp_unslash( $_POST['cycle'] ) : [] ) );
                    Whereabouts::save_rotation( $member_id, [
                        'pattern'         => $post( 'pattern', 'key' ),
                        'start_date'      => $post( 'start_date' ),
                        'homes'           => $homes,
                        'changeover_time' => $post( 'changeover_time' ),
                        'cycle'           => $cycle,
                    ] );
                } elseif ( 'clear_rotation' === $action ) {
                    Whereabouts::clear_rotation( $member_id );
                } else {
                    Whereabouts::set_override( $member_id, $post( 'date' ), $post( 'override_household_id', 'int' ) );
                    Whereabouts::prune_overrides( $member_id );
                }
            }

            wp_send_json_success( [
                'household'   => $dashboard['household'],
                'viewer'      => $dashboard['viewer'],
                'households'  => $dashboard['households'],
                'board'       => $this->storage->get_whereabouts_board( $household_id, $post( 'start' ), $post( 'window', 'int' ) ?: 14 ),
                'permissions' => [ 'organise' => $can_organise, 'manage' => $can_manage ],
            ] );
        }

        switch ( $action ) {
            case 'update_household':
                $this->assert_allowed( $can_manage );
                $this->storage->update_household( $household_id, [ 'name' => $post( 'name' ) ] );
                break;

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
                    $this->storage->add_task( $household_id, $post( 'title' ), $post( 'member_id', 'int' ), $post( 'task_type', 'key' ) ?: 'task', $post( 'due_date' ) );
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

            case 'add_household_info':
                $this->assert_allowed( $can_organise );
                $this->storage->add_household_info( $household_id, $post( 'label' ), $post( 'detail', 'raw' ) );
                break;

            case 'remove_household_info':
                $this->assert_allowed( $can_organise );
                $this->storage->remove_household_info( $household_id, $post( 'info_index', 'int' ) );
                break;
        }

        wp_send_json_success( $this->storage->get_dashboard( $user_id, $household_id, $subject_id ) );
    }

    private function assert_allowed( bool $allowed ): void {
        if ( ! $allowed ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to do that.', 'households' ) ], 403 );
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
