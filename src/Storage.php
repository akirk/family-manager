<?php

namespace FamilyManager;

/**
 * Persistence for households, members, tasks and rewards.
 *
 * Members are WordPress users. Each member also has a `family_member` term
 * (slug `user-<id>`) which is only used to tag tasks and rewards with the
 * member they are assigned to; everything else about a member lives in user
 * meta and in the household's membership map (see Access).
 */
class Storage {
    public const MEMBER_TAXONOMY = 'family_member';
    public const WP_ROLE         = 'family_manager_member';

    public const META_HOUSEHOLD_ID = '_family_manager_household_id';
    public const META_USER_ID      = '_family_manager_user_id';
    public const META_POINTS       = '_family_manager_points';
    /** Household setting: whether tasks earn points and rewards are shown. Off by default. */
    public const META_REWARDS      = '_family_manager_rewards_enabled';

    /* ---------------------------------------------------------------- Households */

    public const META_CURRENT_HOUSEHOLD = '_family_manager_current_household';

    /**
     * The household a user is currently looking at: their remembered choice
     * if they are still a member there, otherwise the first one they belong to.
     */
    public function current_household_id( int $user_id ): int {
        $households = Access::household_ids_for_user( $user_id );
        if ( ! $households ) {
            return 0;
        }
        $current = (int) get_user_meta( $user_id, self::META_CURRENT_HOUSEHOLD, true );
        return in_array( $current, $households, true ) ? $current : $households[0];
    }

    public function switch_household( int $user_id, int $household_id ): bool {
        if ( ! Access::is_member( $user_id, $household_id ) ) {
            return false;
        }
        update_user_meta( $user_id, self::META_CURRENT_HOUSEHOLD, $household_id );
        return true;
    }

    /** @return array[] formatted households the user belongs to. */
    public function get_households_for_user( int $user_id ): array {
        return array_values( array_filter( array_map( [ $this, 'format_household' ], Access::household_ids_for_user( $user_id ) ) ) );
    }

    /**
     * The user's households with what an overview needs: their role there,
     * who else is in it, how much is open, and whether it is the current one.
     */
    public function get_households_overview( int $user_id ): array {
        $current = $this->current_household_id( $user_id );
        $overview = [];
        foreach ( $this->get_households_for_user( $user_id ) as $household ) {
            $id = $household['id'];
            $members = $this->get_members( $id );
            $tasks = $this->get_tasks( $id );
            if ( ! Access::can_organise( $user_id, $id ) ) {
                $tasks = array_values( array_filter( $tasks, static function( array $task ) use ( $user_id ): bool {
                    return 0 === $task['member_id'] || $task['member_id'] === $user_id;
                } ) );
            }
            $open_tasks = 0;
            $appointments = 0;
            foreach ( $tasks as $task ) {
                if ( '0' !== $task['is_done'] ) {
                    continue;
                }
                if ( 'appointment' === $task['task_type'] ) {
                    ++$appointments;
                } else {
                    ++$open_tasks;
                }
            }
            $role = Access::role_in_household( $user_id, $id );
            $overview[] = $household + [
                'is_current'   => $id === $current,
                'role'         => $role,
                'role_label'   => Access::roles()[ $role ] ?? '',
                'can_manage'   => Access::can_manage( $user_id, $id ),
                'member_names' => array_column( $members, 'name' ),
                'open_tasks'   => $open_tasks,
                'appointments' => $appointments,
            ];
        }
        return $overview;
    }

    public function get_or_create_household_for_user( int $user_id ): array {
        $current = $this->current_household_id( $user_id );
        if ( $current ) {
            return $this->format_household( $current );
        }

        $user = get_userdata( $user_id );
        $name = $user && $user->display_name ? sprintf( __( '%s Household', 'family-manager' ), $user->display_name ) : __( 'My Household', 'family-manager' );
        $household_id = $this->create_household( $name, $user_id );

        return $household_id ? $this->format_household( $household_id ) : [];
    }

    public function create_household( string $name, int $admin_user_id ): int {
        $household_id = wp_insert_post( [
            'post_author' => $admin_user_id,
            'post_status' => 'private',
            'post_title'  => $name,
            'post_type'   => 'family_household',
        ], true );

        if ( is_wp_error( $household_id ) ) {
            return 0;
        }

        Access::set_member_role( (int) $household_id, $admin_user_id, Access::ROLE_ADMIN );
        $this->get_or_create_member_term( $admin_user_id );

        return (int) $household_id;
    }

    /**
     * Rename a household and switch its optional features on or off.
     *
     * @param array{name?:string,rewards_enabled?:bool} $settings
     */
    public function update_household( int $household_id, array $settings ): void {
        if ( ! $this->format_household( $household_id ) ) {
            return;
        }
        if ( isset( $settings['name'] ) && '' !== trim( $settings['name'] ) ) {
            wp_update_post( [ 'ID' => $household_id, 'post_title' => trim( $settings['name'] ) ] );
        }
        if ( isset( $settings['rewards_enabled'] ) ) {
            update_post_meta( $household_id, self::META_REWARDS, $settings['rewards_enabled'] ? '1' : '0' );
        }
    }

    public function rewards_enabled( int $household_id ): bool {
        return '1' === get_post_meta( $household_id, self::META_REWARDS, true );
    }

    /* ---------------------------------------------------------------- Dashboard */

    /**
     * Everything the app needs to render one member's view.
     *
     * @param int $viewer_id  The logged-in user.
     * @param int $subject_id Whose view to build; defaults to the viewer. A
     *                        manager of the subject's household may view as them.
     */
    public function get_dashboard( int $viewer_id, int $subject_id = 0 ): array {
        $subject_id = $subject_id ?: $viewer_id;
        if ( ! Access::can_view_user( $viewer_id, $subject_id ) ) {
            return $this->empty_dashboard();
        }

        $household = $viewer_id === $subject_id
            ? $this->get_or_create_household_for_user( $subject_id )
            : $this->format_household( $this->household_for_viewing_as( $viewer_id, $subject_id ) );
        $household_id = isset( $household['id'] ) ? (int) $household['id'] : 0;
        if ( ! $household_id ) {
            return $this->empty_dashboard();
        }

        $subject_role = Access::role_in_household( $subject_id, $household_id );
        $tasks = $this->get_tasks( $household_id );
        $rewards = $this->get_rewards( $household_id );

        // Children only see what is theirs or shared with the whole household.
        if ( ! Access::can_organise( $subject_id, $household_id ) ) {
            $mine = static function( array $item ) use ( $subject_id ): bool {
                return 0 === $item['member_id'] || $item['member_id'] === $subject_id;
            };
            $tasks = array_values( array_filter( $tasks, $mine ) );
            $rewards = array_values( array_filter( $rewards, $mine ) );
        }

        return [
            'household'   => $household,
            'viewer'      => $this->format_member( $viewer_id, $household_id ),
            'subject'     => $this->format_member( $subject_id, $household_id ),
            'permissions' => [
                'manage'   => Access::can_manage( $viewer_id, $household_id ),
                'organise' => Access::can_organise( $subject_id, $household_id ) && Access::can_organise( $viewer_id, $household_id ),
                'viewing_as_other' => $viewer_id !== $subject_id,
            ],
            'roles'       => Access::roles(),
            'households'  => $this->get_households_for_user( $viewer_id ),
            'members'     => $this->get_members( $household_id ),
            'birthdays'   => $this->get_upcoming_birthdays( $household_id ),
            'tasks'       => $tasks,
            'rewards'     => $rewards,
        ];
    }

    /**
     * Which household to show when a manager views as another member: the
     * manager's current one if the subject belongs to it, else the first
     * household the manager runs that the subject is part of.
     */
    private function household_for_viewing_as( int $viewer_id, int $subject_id ): int {
        $current = $this->current_household_id( $viewer_id );
        if ( $current && Access::is_member( $subject_id, $current ) && Access::can_manage( $viewer_id, $current ) ) {
            return $current;
        }
        foreach ( Access::household_ids_for_user( $subject_id ) as $household_id ) {
            if ( Access::can_manage( $viewer_id, $household_id ) ) {
                return $household_id;
            }
        }
        return 0;
    }

    private function empty_dashboard(): array {
        return [
            'household'   => [],
            'viewer'      => [],
            'subject'     => [],
            'permissions' => [ 'manage' => false, 'organise' => false, 'viewing_as_other' => false ],
            'roles'       => Access::roles(),
            'households'  => [],
            'members'     => [],
            'birthdays'   => [],
            'tasks'       => [],
            'rewards'     => [],
        ];
    }

    /* ---------------------------------------------------------------- Members */

    public function get_members( int $household_id ): array {
        $members = [];
        foreach ( Access::members_of( $household_id ) as $user_id => $role ) {
            $member = $this->format_member( $user_id, $household_id );
            if ( $member ) {
                $members[] = $member;
            }
        }
        usort( $members, static function( array $a, array $b ): int {
            $order = array_flip( array_keys( Access::roles() ) );
            if ( $order[ $a['role'] ] !== $order[ $b['role'] ] ) {
                return $order[ $a['role'] ] <=> $order[ $b['role'] ];
            }
            return strcasecmp( $a['name'], $b['name'] );
        } );
        return $members;
    }

    /**
     * Add a member to a household, creating a WordPress user for them.
     *
     * If an email is given and a user with that email already exists, that
     * user is linked instead of creating a new one.
     *
     * @return int User ID, or 0 on failure.
     */
    public function add_member( int $household_id, string $name, string $role = Access::ROLE_CHILD, string $email = '', string $password = '' ): int {
        $name = trim( $name );
        if ( '' === $name ) {
            return 0;
        }

        $user_id = 0;
        if ( '' !== $email ) {
            $existing = get_user_by( 'email', $email );
            if ( $existing ) {
                $user_id = (int) $existing->ID;
            }
        }

        if ( ! $user_id ) {
            $login = $this->unique_login( $name );
            $result = wp_insert_user( [
                'user_login'   => $login,
                'user_pass'    => '' !== $password ? $password : wp_generate_password( 20 ),
                'user_email'   => $email,
                'display_name' => $name,
                'first_name'   => $name,
                'role'         => self::WP_ROLE,
                'show_admin_bar_front' => 'false',
            ] );
            if ( is_wp_error( $result ) ) {
                return 0;
            }
            $user_id = (int) $result;
        }

        Access::set_member_role( $household_id, $user_id, $role );
        $this->get_or_create_member_term( $user_id );
        if ( '' === get_user_meta( $user_id, self::META_POINTS, true ) ) {
            update_user_meta( $user_id, self::META_POINTS, 0 );
        }

        return $user_id;
    }

    public function remove_member( int $household_id, int $user_id ): void {
        if ( Access::ROLE_ADMIN === Access::role_in_household( $user_id, $household_id ) ) {
            // Never orphan a household: keep at least one admin.
            $admins = array_keys( Access::members_of( $household_id ), Access::ROLE_ADMIN, true );
            if ( count( $admins ) < 2 ) {
                return;
            }
        }
        Access::remove_member( $household_id, $user_id );
    }

    public function get_or_create_member_term( int $user_id ): int {
        $slug = 'user-' . $user_id;
        $term = get_term_by( 'slug', $slug, self::MEMBER_TAXONOMY );
        if ( $term ) {
            return (int) $term->term_id;
        }
        $user = get_userdata( $user_id );
        $inserted = wp_insert_term( $user ? $user->display_name : $slug, self::MEMBER_TAXONOMY, [ 'slug' => $slug ] );
        if ( is_wp_error( $inserted ) ) {
            return 0;
        }
        update_term_meta( (int) $inserted['term_id'], self::META_USER_ID, $user_id );
        return (int) $inserted['term_id'];
    }

    public function add_points( int $user_id, int $delta ): int {
        $points = (int) get_user_meta( $user_id, self::META_POINTS, true ) + $delta;
        update_user_meta( $user_id, self::META_POINTS, $points );
        return $points;
    }

    /* ---------------------------------------------------------------- Profiles */

    public const PROFILE_FIELDS = [
        'birthdate'     => 'date',
        'allergies'     => 'multiline',
        'shoe_size'     => 'line',
        'clothing_size' => 'line',
        'notes'         => 'multiline',
    ];

    public function get_profile( int $user_id, int $household_id ): array {
        $profile = $this->format_member( $user_id, $household_id );
        if ( ! $profile ) {
            return [];
        }
        foreach ( self::PROFILE_FIELDS as $field => $type ) {
            $profile[ $field ] = (string) get_user_meta( $user_id, '_family_manager_' . $field, true );
        }
        $profile['age'] = $this->age_from_birthdate( $profile['birthdate'] );
        return $profile;
    }

    public function save_profile( int $user_id, array $fields ): void {
        foreach ( self::PROFILE_FIELDS as $field => $type ) {
            if ( ! array_key_exists( $field, $fields ) ) {
                continue;
            }
            $value = (string) $fields[ $field ];
            if ( 'date' === $type ) {
                $value = $this->normalize_date( $value );
            } elseif ( 'multiline' === $type ) {
                $value = sanitize_textarea_field( $value );
            } else {
                $value = sanitize_text_field( $value );
            }
            update_user_meta( $user_id, '_family_manager_' . $field, $value );
        }
    }

    /** Members with a birthdate, ordered by how soon their next birthday is. */
    public function get_upcoming_birthdays( int $household_id ): array {
        $today = new \DateTimeImmutable( current_time( 'Y-m-d' ) );
        $list = [];
        foreach ( Access::members_of( $household_id ) as $user_id => $role ) {
            $birthdate = (string) get_user_meta( $user_id, '_family_manager_birthdate', true );
            if ( '' === $birthdate ) {
                continue;
            }
            $born = \DateTimeImmutable::createFromFormat( '!Y-m-d', $birthdate );
            if ( ! $born ) {
                continue;
            }
            $next = $born->setDate( (int) $today->format( 'Y' ), (int) $born->format( 'm' ), (int) $born->format( 'd' ) );
            if ( $next < $today ) {
                $next = $next->modify( '+1 year' );
            }
            $user = get_userdata( $user_id );
            $list[] = [
                'id'          => $user_id,
                'name'        => $user ? $user->display_name : '',
                'date'        => $next->format( 'Y-m-d' ),
                'days_until'  => (int) $today->diff( $next )->days,
                'turning'     => (int) $next->format( 'Y' ) - (int) $born->format( 'Y' ),
            ];
        }
        usort( $list, static function( array $a, array $b ): int {
            return $a['days_until'] <=> $b['days_until'];
        } );
        return $list;
    }

    private function age_from_birthdate( string $birthdate ): ?int {
        $born = '' !== $birthdate ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $birthdate ) : null;
        return $born ? (int) $born->diff( new \DateTimeImmutable( current_time( 'Y-m-d' ) ) )->y : null;
    }

    /* ---------------------------------------------------------------- Tasks */

    public function get_tasks( int $household_id ): array {
        $tasks = array_map( [ $this, 'format_task' ], $this->get_related_posts( $household_id, 'family_task' ) );
        usort( $tasks, static function( array $a, array $b ): int {
            if ( $a['is_done'] !== $b['is_done'] ) {
                return (int) $a['is_done'] <=> (int) $b['is_done'];
            }
            return strcmp( $a['due_date'] ?: '9999-12-31', $b['due_date'] ?: '9999-12-31' );
        } );
        return $tasks;
    }

    public function add_task( int $household_id, string $title, int $member_id = 0, string $task_type = 'task', int $points = 0, string $due_date = '' ): int {
        $member_id = $this->normalize_member_id( $household_id, $member_id );
        $task_id = wp_insert_post( [
            'post_author' => $this->get_household_owner_user_id( $household_id ),
            'post_parent' => $household_id,
            'post_status' => 'private',
            'post_title'  => $title,
            'post_type'   => 'family_task',
        ], true );

        if ( is_wp_error( $task_id ) ) {
            return 0;
        }

        update_post_meta( $task_id, self::META_HOUSEHOLD_ID, $household_id );
        update_post_meta( $task_id, '_family_manager_task_type', in_array( $task_type, [ 'task', 'appointment' ], true ) ? $task_type : 'task' );
        update_post_meta( $task_id, self::META_POINTS, $points );
        update_post_meta( $task_id, '_family_manager_due_date', $this->normalize_date( $due_date ) );
        update_post_meta( $task_id, '_family_manager_is_done', 0 );
        $this->assign_member( $task_id, $member_id );

        return (int) $task_id;
    }

    /**
     * @param int $actor_id User doing the toggling; recorded for the audit trail
     *                      when a manager acts on someone else's behalf.
     */
    public function toggle_task( int $household_id, int $task_id, int $actor_id = 0 ): bool {
        $task = get_post( $task_id );
        if ( ! $task || 'family_task' !== $task->post_type || (int) get_post_meta( $task_id, self::META_HOUSEHOLD_ID, true ) !== $household_id ) {
            return false;
        }

        $is_done = (int) get_post_meta( $task_id, '_family_manager_is_done', true ) ? 0 : 1;
        update_post_meta( $task_id, '_family_manager_is_done', $is_done );
        update_post_meta( $task_id, '_family_manager_completed_at', $is_done ? current_time( 'mysql' ) : '' );
        update_post_meta( $task_id, '_family_manager_completed_by', $is_done ? $actor_id : 0 );

        $member_id = $this->get_assigned_member_id( $task_id );
        $points = (int) get_post_meta( $task_id, self::META_POINTS, true );
        if ( $member_id && $points ) {
            $this->add_points( $member_id, $is_done ? $points : -1 * $points );
        }

        return true;
    }

    /* ---------------------------------------------------------------- Rewards */

    public function get_rewards( int $household_id ): array {
        return array_map( [ $this, 'format_reward' ], $this->get_related_posts( $household_id, 'family_reward' ) );
    }

    public function add_reward( int $household_id, string $title, int $member_id = 0, int $points = 0 ): int {
        $member_id = $this->normalize_member_id( $household_id, $member_id );
        $reward_id = wp_insert_post( [
            'post_author' => $this->get_household_owner_user_id( $household_id ),
            'post_parent' => $household_id,
            'post_status' => 'private',
            'post_title'  => $title,
            'post_type'   => 'family_reward',
        ], true );

        if ( is_wp_error( $reward_id ) ) {
            return 0;
        }

        update_post_meta( $reward_id, self::META_HOUSEHOLD_ID, $household_id );
        update_post_meta( $reward_id, self::META_POINTS, $points );
        update_post_meta( $reward_id, '_family_manager_redeemed_at', '' );
        $this->assign_member( $reward_id, $member_id );

        return (int) $reward_id;
    }

    /* ---------------------------------------------------------------- Formatting */

    private function get_related_posts( int $household_id, string $post_type ): array {
        return array_map( 'intval', get_posts( [
            'fields'           => 'ids',
            'meta_key'         => self::META_HOUSEHOLD_ID,
            'meta_value'       => $household_id,
            'numberposts'      => -1,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'post_status'      => 'private',
            'post_type'        => $post_type,
            'suppress_filters' => false,
        ] ) );
    }

    private function format_household( int $household_id ): array {
        $household = $household_id ? get_post( $household_id ) : null;
        if ( ! $household || 'family_household' !== $household->post_type ) {
            return [];
        }
        return [
            'id'              => $household_id,
            'name'            => $household->post_title,
            'created_at'      => $household->post_date,
            'rewards_enabled' => $this->rewards_enabled( $household_id ),
        ];
    }

    public function format_member( int $user_id, int $household_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }
        $role = Access::role_in_household( $user_id, $household_id );
        return [
            'id'           => $user_id,
            'household_id' => $household_id,
            'name'         => $user->display_name,
            'login'        => $user->user_login,
            'role'         => $role,
            'role_label'   => Access::roles()[ $role ] ?? '',
            'can_organise' => Access::can_organise( $user_id, $household_id ),
            'points'       => (string) (int) get_user_meta( $user_id, self::META_POINTS, true ),
        ];
    }

    private function format_task( int $task_id ): array {
        $member_id = $this->get_assigned_member_id( $task_id );
        $member = $member_id ? get_userdata( $member_id ) : null;
        return [
            'id'           => $task_id,
            'household_id' => (int) get_post_meta( $task_id, self::META_HOUSEHOLD_ID, true ),
            'member_id'    => $member_id,
            'member_name'  => $member ? $member->display_name : '',
            'title'        => get_post_field( 'post_title', $task_id ),
            'task_type'    => get_post_meta( $task_id, '_family_manager_task_type', true ) ?: 'task',
            'points'       => (string) (int) get_post_meta( $task_id, self::META_POINTS, true ),
            'due_date'     => get_post_meta( $task_id, '_family_manager_due_date', true ),
            'is_done'      => (string) (int) get_post_meta( $task_id, '_family_manager_is_done', true ),
        ];
    }

    private function format_reward( int $reward_id ): array {
        $member_id = $this->get_assigned_member_id( $reward_id );
        $member = $member_id ? get_userdata( $member_id ) : null;
        return [
            'id'           => $reward_id,
            'household_id' => (int) get_post_meta( $reward_id, self::META_HOUSEHOLD_ID, true ),
            'member_id'    => $member_id,
            'member_name'  => $member ? $member->display_name : '',
            'title'        => get_post_field( 'post_title', $reward_id ),
            'points'       => (string) (int) get_post_meta( $reward_id, self::META_POINTS, true ),
            'redeemed_at'  => get_post_meta( $reward_id, '_family_manager_redeemed_at', true ),
        ];
    }

    /* ---------------------------------------------------------------- Helpers */

    private function normalize_member_id( int $household_id, int $member_id ): int {
        return $member_id && Access::is_member( $member_id, $household_id ) ? $member_id : 0;
    }

    private function assign_member( int $post_id, int $member_id ): void {
        $term_id = $member_id ? $this->get_or_create_member_term( $member_id ) : 0;
        wp_set_object_terms( $post_id, $term_id ? [ $term_id ] : [], self::MEMBER_TAXONOMY, false );
    }

    private function get_assigned_member_id( int $post_id ): int {
        $terms = wp_get_object_terms( $post_id, self::MEMBER_TAXONOMY );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return 0;
        }
        return (int) get_term_meta( $terms[0]->term_id, self::META_USER_ID, true );
    }

    private function get_household_owner_user_id( int $household_id ): int {
        $household = get_post( $household_id );
        return $household ? (int) $household->post_author : 0;
    }

    private function unique_login( string $name ): string {
        $base = sanitize_user( strtolower( remove_accents( $name ) ), true );
        $base = preg_replace( '/[^a-z0-9]+/', '', $base ) ?: 'member';
        $login = $base;
        $i = 2;
        while ( username_exists( $login ) ) {
            $login = $base . $i++;
        }
        return $login;
    }

    private function normalize_date( string $date ): string {
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
    }
}
