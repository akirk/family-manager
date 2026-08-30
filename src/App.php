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
            'post_types'          => [ Access::PERSON, Storage::FACT, Storage::ITEM, Storage::TASK ],
            'taxonomies'          => [ Access::TAXONOMY ],
        ] );

        $this->storage = new Storage();

        // People who log in get a WordPress account with this role: enough to
        // reach the app, nothing else. People who never log in get no account.
        $this->app->add_role( 'member', __( 'Household Member', 'households' ), [ 'read' => true ] );

        Access::init();

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'template_redirect', [ $this, 'route_by_home' ] );
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
     * digits, so a home can never shadow `where` or `person`.
     */
    protected function setup_routes(): void {
        // The index: every home you belong to.
        $this->app->route( '' );
        // Who is at which home, day by day, across the homes you belong to.
        $this->app->route( 'where', 'where.php' );
        // A person, and what travels with them between homes.
        $this->app->route( 'person/{person_id}', 'person.php' );
        // One home: its people, tasks, facts and things.
        $this->app->route( '{id}', 'home.php' );
        // Managing that home: who is in it, who administers it, its name.
        $this->app->route( '{id}/manage', 'manage.php' );
        // That home, seen as one of the people in it.
        $this->app->route( '{id}/as/{person_id}', 'home.php' );
    }

    protected function setup_menu(): void {
        $base = home_url( '/' . $this->get_url_path() . '/' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            $this->app->add_menu_item( 'index', __( 'Your homes', 'households' ), $base );
            return;
        }

        $person_id = Access::person_for_user( $user_id );
        $homes = $this->storage->get_homes_for_person( $person_id );
        $this->app->add_menu_item( 'index', count( $homes ) > 1 ? __( 'Your homes', 'households' ) : __( 'Home', 'households' ), $base );

        // Each home is a place you go to, not a mode you switch into.
        foreach ( $homes as $home ) {
            $this->app->add_menu_item( 'home-' . $home['id'], $home['name'], $base . $home['id'] . '/' );
        }

        $this->app->add_menu_item( 'where', __( 'Who is where', 'households' ), $base . 'where/' );

        $open = $this->home_in_view( $user_id );
        if ( $open && Access::can_manage( $user_id, $open ) ) {
            $this->app->add_menu_item( 'manage', __( 'Manage this home', 'households' ), $base . $open . '/manage/' );
            foreach ( $this->storage->get_people( $open ) as $person ) {
                if ( $person['id'] === $person_id ) {
                    continue;
                }
                $this->app->add_menu_item(
                    'view-as-' . $person['id'],
                    sprintf( __( 'View as %s', 'households' ), $person['name'] ),
                    $base . $open . '/as/' . $person['id'] . '/'
                );
            }
        }

        if ( $person_id ) {
            $this->app->add_user_menu_item( 'me', __( 'My page', 'households' ), $base . 'person/' . $person_id . '/' );
        }
    }

    /**
     * The home the current page is about: the one in the URL, else the last one
     * visited. Used for the menu and for the views that span homes.
     */
    public function home_in_view( int $user_id ): int {
        $from_url = (int) get_query_var( 'id' );
        if ( $from_url && Access::is_member( Access::person_for_user( $user_id ), $from_url ) ) {
            return $from_url;
        }
        return $this->storage->last_home_id( $user_id );
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
     * Every home is addressed by its term ID, so both of these run before
     * anything is rendered: one sends a lone householder straight in, the other
     * turns away a request for a home the viewer does not belong to.
     */
    public function route_by_home(): void {
        $path = $this->app_request_path();
        if ( null === $path || ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $person_id = Access::person_for_user( $user_id );
        $index = home_url( '/' . $this->get_url_path() . '/' );

        if ( '' === $path ) {
            $homes = Access::home_ids_for_person( $person_id );
            if ( 1 === count( $homes ) ) {
                wp_safe_redirect( $index . $homes[0] . '/' );
                exit;
            }
            return;
        }

        if ( ! preg_match( '#^(\d+)(?:/|$)#', $path, $matches ) ) {
            return;
        }

        $home_id = (int) $matches[1];
        if ( ! Access::is_member( $person_id, $home_id ) ) {
            wp_safe_redirect( $index );
            exit;
        }

        // Managing a home, and looking at it as someone else, are both things
        // only its administrators do. Turning them away here means the page
        // never renders controls that every action would refuse anyway.
        if ( preg_match( '#^\d+/(manage|as)(?:/|$)#', $path ) && ! Access::can_manage( $user_id, $home_id ) ) {
            wp_safe_redirect( $index . $home_id . '/' );
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

    /**
     * A home is a term; everything in it is a post tagged with that term.
     *
     * The taxonomy is closed on purpose. Terms are global in a way private
     * posts are not, and a term named after someone's home would otherwise be
     * readable through term archives and the REST namespace.
     */
    public function register_post_types(): void {
        register_taxonomy( Access::TAXONOMY, [ Access::PERSON, Storage::FACT, Storage::ITEM, Storage::TASK ], [
            'labels'            => [
                'name'          => __( 'Homes', 'households' ),
                'singular_name' => __( 'Home', 'households' ),
            ],
            'public'            => false,
            'show_ui'           => current_user_can( 'manage_options' ),
            'show_in_rest'      => false,
            // Nothing is nested yet, but a flat taxonomy cannot later hold a
            // flat inside a building without revisiting every query.
            'hierarchical'      => true,
            'show_admin_column' => true,
        ] );

        $post_types = [
            Access::PERSON => [
                'singular' => __( 'Person', 'households' ),
                'plural'   => __( 'People', 'households' ),
                // The editor holds what is true of someone in prose, and
                // revisions are how that reads back as a history.
                'supports' => [ 'title', 'editor', 'revisions', 'author' ],
            ],
            Storage::FACT => [
                'singular' => __( 'House Fact', 'households' ),
                'plural'   => __( 'House Facts', 'households' ),
                'supports' => [ 'title', 'editor', 'revisions' ],
            ],
            Storage::ITEM => [
                'singular' => __( 'Item', 'households' ),
                'plural'   => __( 'Items', 'households' ),
                'supports' => [ 'title', 'editor', 'revisions' ],
            ],
            Storage::TASK => [
                'singular' => __( 'Task', 'households' ),
                'plural'   => __( 'Tasks', 'households' ),
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
    }

    public function handle_dashboard_request(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Please log in to open your home.', 'households' ) ], 401 );
        }
        check_ajax_referer( 'households_app', 'nonce' );

        $user_id = get_current_user_id();
        $viewer_person = Access::person_for_user( $user_id );
        $action = isset( $_POST['household_action'] ) ? sanitize_key( wp_unslash( $_POST['household_action'] ) ) : 'get';

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

        if ( 'get_homes' === $action ) {
            wp_send_json_success( [ 'homes' => $this->storage->get_homes_overview( $user_id ) ] );
        }

        // The person page addresses someone directly rather than through a home.
        if ( 'get_person' === $action || 'save_person' === $action ) {
            $person_id = $post( 'person_id', 'int' ) ?: $viewer_person;
            $this->assert_allowed( Access::can_view_person( $user_id, $person_id ) );
            if ( 'save_person' === $action ) {
                $this->storage->save_person( $person_id, [
                    'about'     => $post( 'about', 'raw' ),
                    'birthdate' => $post( 'birthdate' ),
                ] );
            }
            wp_send_json_success( [ 'person' => $this->storage->get_person( $person_id ) ] );
        }

        // The home is named by the page asking. A request that names one the
        // viewer does not belong to is refused rather than quietly answered
        // about a different home; only a request that names none at all — the
        // views that span homes — falls back to the last one visited.
        if ( $post( 'home_id', 'int' ) ) {
            $home_id = $post( 'home_id', 'int' );
            $this->assert_allowed( Access::is_member( $viewer_person, $home_id ) );
        } else {
            $home_id = $this->storage->last_home_id( $user_id );
        }
        if ( ! $home_id ) {
            wp_send_json_error( [ 'message' => __( 'You do not belong to a home yet.', 'households' ) ], 404 );
        }
        $this->storage->remember_home( $user_id, $home_id );

        $subject_id = $post( 'view_as', 'int' ) ?: $viewer_person;
        if ( $subject_id !== $viewer_person ) {
            $this->assert_allowed( Access::can_view_person( $user_id, $subject_id ) );
        }

        $can_manage = current_user_can( 'manage_household', $home_id );
        $can_organise = current_user_can( 'organise_household', $home_id );

        // Whereabouts answers with the board rather than the dashboard.
        if ( in_array( $action, [ 'get_whereabouts', 'save_rotation', 'clear_rotation', 'set_override' ], true ) ) {
            if ( 'get_whereabouts' !== $action ) {
                $this->assert_allowed( $can_organise );
                $person_id = $post( 'person_id', 'int' );
                $this->assert_allowed( $person_id && Access::is_member( $person_id, $home_id ) );

                if ( 'save_rotation' === $action ) {
                    $homes = array_map( 'absint', (array) ( isset( $_POST['homes'] ) ? wp_unslash( $_POST['homes'] ) : [] ) );
                    $cycle = array_map( 'absint', (array) ( isset( $_POST['cycle'] ) ? wp_unslash( $_POST['cycle'] ) : [] ) );
                    Whereabouts::save_rotation( $person_id, [
                        'pattern'         => $post( 'pattern', 'key' ),
                        'start_date'      => $post( 'start_date' ),
                        'homes'           => $homes,
                        'changeover_time' => $post( 'changeover_time' ),
                        'cycle'           => $cycle,
                    ] );
                } elseif ( 'clear_rotation' === $action ) {
                    Whereabouts::clear_rotation( $person_id );
                } else {
                    Whereabouts::set_override( $person_id, $post( 'date' ), $post( 'override_home_id', 'int' ) );
                    Whereabouts::prune_overrides( $person_id );
                }
            }

            wp_send_json_success( [
                'board'       => $this->storage->get_whereabouts_board( $home_id, $post( 'start' ), $post( 'window', 'int' ) ?: 14 ),
                'homes'       => $this->storage->get_homes_for_person( $viewer_person ),
                'permissions' => [ 'organise' => $can_organise, 'manage' => $can_manage ],
            ] );
        }

        switch ( $action ) {
            case 'update_home':
                $this->assert_allowed( $can_manage );
                $this->storage->update_home( $home_id, $post( 'name' ) );
                break;

            case 'add_person':
                $this->assert_allowed( $can_manage );
                $this->storage->add_person( $home_id, $post( 'name' ), [
                    'email'     => $post( 'email', 'email' ),
                    'password'  => $post( 'password', 'raw' ),
                    'is_child'  => (bool) $post( 'is_child', 'int' ),
                    'label'     => $post( 'label' ),
                    'birthdate' => $post( 'birthdate' ),
                ] );
                break;

            case 'update_person':
                $this->assert_allowed( $can_manage );
                $person_id = $post( 'person_id', 'int' );
                if ( Access::is_member( $person_id, $home_id ) ) {
                    $this->storage->save_person( $person_id, [
                        'is_child' => (bool) $post( 'is_child', 'int' ),
                        'label'    => $post( 'label' ),
                    ] );
                }
                break;

            case 'remove_person':
                $this->assert_allowed( $can_manage );
                $person_id = $post( 'person_id', 'int' );
                // The record survives leaving; only the membership goes.
                if ( $person_id && $person_id !== $viewer_person && Access::is_member( $person_id, $home_id ) ) {
                    Access::leave( $person_id, $home_id );
                }
                break;

            case 'set_admin':
                $this->assert_allowed( $can_manage );
                $person_id = $post( 'person_id', 'int' );
                $target_user = Access::user_for_person( $person_id );
                // An administrator may not drop themselves and leave the home
                // with nobody who can manage it.
                if ( $target_user && Access::is_member( $person_id, $home_id ) && $target_user !== $user_id ) {
                    Access::set_admin( $home_id, $target_user, (bool) $post( 'is_admin', 'int' ) );
                }
                break;

            case 'add_task':
                $this->assert_allowed( $can_organise );
                $this->storage->add_task( $home_id, $post( 'title' ), $post( 'person_id', 'int' ), $post( 'task_type', 'key' ) ?: 'task', $post( 'due_date' ) );
                break;

            case 'toggle_task':
                $task_id = $post( 'task_id', 'int' );
                // Anyone may tick what they can see; the dashboard is what they
                // can see, so that is what is checked against.
                $visible = array_column( $this->storage->get_dashboard( $user_id, $home_id, $subject_id )['tasks'] ?? [], 'id' );
                if ( $task_id && ( $can_organise || in_array( $task_id, $visible, true ) ) ) {
                    $this->storage->toggle_task( $home_id, $task_id );
                }
                break;

            case 'remove_task':
                $this->assert_allowed( $can_organise );
                $this->storage->remove_task( $home_id, $post( 'task_id', 'int' ) );
                break;

            case 'add_note':
                $this->assert_allowed( $can_organise );
                $this->storage->add_note( $home_id, $this->note_type( $post( 'kind', 'key' ) ), $post( 'title' ), $post( 'detail', 'raw' ) );
                break;

            case 'update_note':
                $this->assert_allowed( $can_organise );
                $this->storage->update_note( $home_id, $this->note_type( $post( 'kind', 'key' ) ), $post( 'note_id', 'int' ), $post( 'title' ), $post( 'detail', 'raw' ) );
                break;

            case 'remove_note':
                $this->assert_allowed( $can_organise );
                $this->storage->remove_note( $home_id, $this->note_type( $post( 'kind', 'key' ) ), $post( 'note_id', 'int' ) );
                break;
        }

        wp_send_json_success( $this->storage->get_dashboard( $user_id, $home_id, $subject_id ) );
    }

    /** Facts and things are the same record; the page says which it is asking about. */
    private function note_type( string $kind ): string {
        return 'item' === $kind ? Storage::ITEM : Storage::FACT;
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
