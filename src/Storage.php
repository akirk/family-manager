<?php

namespace Households;

/**
 * Persistence for households, members and tasks.
 *
 * Members are WordPress users. Each member also has a `household_member` term
 * (slug `user-<id>`) which is only used to tag tasks with the member they are
 * assigned to; everything else about a member lives in user meta and in the
 * household's membership map (see Access).
 */
class Storage {
    public const MEMBER_TAXONOMY = 'household_member';
    public const WP_ROLE         = 'households_member';

    public const META_HOUSEHOLD_ID = '_households_household_id';
    public const META_USER_ID      = '_households_user_id';

    /* ---------------------------------------------------------------- Households */

    public const META_LAST_HOUSEHOLD = '_households_last_household';

    /**
     * The last home this user was looking at.
     *
     * Every home has its own URL, so this is not a mode anyone switches into:
     * it is a note of where they were, used to pick a landing spot and to give
     * the cross-home views somewhere to look from. It falls back to the first
     * home they belong to, and to nothing at all if they belong to none.
     */
    public function last_household_id( int $user_id ): int {
        $households = Access::household_ids_for_user( $user_id );
        if ( ! $households ) {
            return 0;
        }
        $last = (int) get_user_meta( $user_id, self::META_LAST_HOUSEHOLD, true );
        return in_array( $last, $households, true ) ? $last : $households[0];
    }

    /** Records a visit. Called when a home is opened, not by a switcher. */
    public function remember_household( int $user_id, int $household_id ): bool {
        if ( ! Access::is_member( $user_id, $household_id ) ) {
            return false;
        }
        update_user_meta( $user_id, self::META_LAST_HOUSEHOLD, $household_id );
        return true;
    }

    /** @return array[] formatted households the user belongs to. */
    public function get_households_for_user( int $user_id ): array {
        return array_values( array_filter( array_map( [ $this, 'format_household' ], Access::household_ids_for_user( $user_id ) ) ) );
    }

    /**
     * The user's homes with what the index needs to be worth landing on: their
     * role there, who is under that roof today, who else belongs to it, and how
     * much is open.
     */
    public function get_households_overview( int $user_id ): array {
        $last = $this->last_household_id( $user_id );
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
                'is_last'      => $id === $last,
                'role'         => $role,
                'role_label'   => Access::roles()[ $role ] ?? '',
                'can_manage'   => Access::can_manage( $user_id, $id ),
                'member_names' => array_column( $members, 'name' ),
                'here_names'   => $this->who_is_here( $id ),
                'open_tasks'   => $open_tasks,
                'appointments' => $appointments,
            ];
        }
        return $overview;
    }

    /** Someone with no home at all gets one, so the app is never empty. */
    public function get_or_create_household_for_user( int $user_id ): array {
        $existing = $this->last_household_id( $user_id );
        if ( $existing ) {
            return $this->format_household( $existing );
        }

        $user = get_userdata( $user_id );
        $name = $user && $user->display_name ? sprintf( __( '%s Household', 'households' ), $user->display_name ) : __( 'My Household', 'households' );
        $household_id = $this->create_household( $name, $user_id );

        return $household_id ? $this->format_household( $household_id ) : [];
    }

    public function create_household( string $name, int $admin_user_id ): int {
        $household_id = wp_insert_post( [
            'post_author' => $admin_user_id,
            'post_status' => 'private',
            'post_title'  => $name,
            'post_type'   => 'household',
        ], true );

        if ( is_wp_error( $household_id ) ) {
            return 0;
        }

        Access::set_member_role( (int) $household_id, $admin_user_id, Access::ROLE_ADMIN );
        $this->get_or_create_member_term( $admin_user_id );

        return (int) $household_id;
    }

    /**
     * Rename a household.
     *
     * @param array{name?:string} $settings
     */
    public function update_household( int $household_id, array $settings ): void {
        if ( ! $this->format_household( $household_id ) ) {
            return;
        }
        if ( isset( $settings['name'] ) && '' !== trim( $settings['name'] ) ) {
            wp_update_post( [ 'ID' => $household_id, 'post_title' => trim( $settings['name'] ) ] );
        }
    }

    /* ---------------------------------------------------------------- Dashboard */

    /**
     * Everything the app needs to render one home, as one member sees it.
     *
     * The home is named by the caller rather than inferred from a remembered
     * choice: every home has its own URL, and this answers for the one asked
     * about, or for nothing if the viewer does not belong there.
     *
     * @param int $viewer_id    The logged-in user.
     * @param int $household_id The home being opened.
     * @param int $subject_id   Whose view to build; defaults to the viewer. A
     *                          manager of that home may view as one of its members.
     */
    public function get_dashboard( int $viewer_id, int $household_id, int $subject_id = 0 ): array {
        $subject_id = $subject_id ?: $viewer_id;

        $household = $this->format_household( $household_id );
        if ( ! $household || ! Access::is_member( $viewer_id, $household_id ) ) {
            return $this->empty_dashboard();
        }

        // Viewing as someone else only makes sense for a member of this home,
        // and only for someone allowed to look.
        if ( $subject_id !== $viewer_id
            && ( ! Access::is_member( $subject_id, $household_id )
                || ! Access::can_manage( $viewer_id, $household_id )
                || ! Access::can_view_user( $viewer_id, $subject_id ) ) ) {
            return $this->empty_dashboard();
        }

        $tasks = $this->get_tasks( $household_id );

        // Children only see what is theirs or shared with the whole household.
        if ( ! Access::can_organise( $subject_id, $household_id ) ) {
            $tasks = array_values( array_filter( $tasks, static function( array $task ) use ( $subject_id ): bool {
                return 0 === $task['member_id'] || $task['member_id'] === $subject_id;
            } ) );
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
            'whereabouts' => $this->get_whereabouts_summary( $household_id ),
            'info'        => $this->get_household_info( $household_id ),
            'tasks'       => $tasks,
        ];
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
            'whereabouts' => [ 'members' => [], 'next_handoff' => null ],
            'info'        => [],
            'tasks'       => [],
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
            $profile[ $field ] = (string) get_user_meta( $user_id, '_households_' . $field, true );
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
            update_user_meta( $user_id, '_households_' . $field, $value );
        }
    }

    /** Members with a birthdate, ordered by how soon their next birthday is. */
    public function get_upcoming_birthdays( int $household_id ): array {
        $today = new \DateTimeImmutable( current_time( 'Y-m-d' ) );
        $list = [];
        foreach ( Access::members_of( $household_id ) as $user_id => $role ) {
            $birthdate = (string) get_user_meta( $user_id, '_households_birthdate', true );
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

    /* ---------------------------------------------------------------- Whereabouts */

    /**
     * The whereabouts board: a day-by-day grid of which home each member is at,
     * plus the handovers those days imply.
     *
     * Everything is expressed from `$household_id`'s point of view — `is_here`
     * is what someone standing in this kitchen wants to know.
     */
    public function get_whereabouts_board( int $household_id, string $start = '', int $days = 14 ): array {
        $days = max( 1, min( 56, $days ) );
        $start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ? $start : current_time( 'Y-m-d' );
        $today = current_time( 'Y-m-d' );

        $dates = [];
        $cursor = new \DateTimeImmutable( $start, new \DateTimeZone( 'UTC' ) );
        for ( $i = 0; $i < $days; $i++ ) {
            $dates[] = [
                'date'       => $cursor->format( 'Y-m-d' ),
                'weekday'    => wp_date( 'D', $cursor->getTimestamp(), new \DateTimeZone( 'UTC' ) ),
                'day'        => $cursor->format( 'j' ),
                'month'      => wp_date( 'M', $cursor->getTimestamp(), new \DateTimeZone( 'UTC' ) ),
                'is_today'   => $cursor->format( 'Y-m-d' ) === $today,
                'is_weekend' => in_array( (int) $cursor->format( 'N' ), [ 6, 7 ], true ),
            ];
            $cursor = $cursor->modify( '+1 day' );
        }

        $members = $this->members_with_days( $household_id, $start, $days );

        return [
            'start'      => $start,
            'days'       => $days,
            'today'      => $today,
            'dates'      => $dates,
            'members'    => $members,
            'handoffs'   => $this->collect_handoffs( $members, $household_id ),
            'patterns'   => $this->format_patterns(),
            'households' => $this->get_households_in_play( $household_id ),
        ];
    }

    /**
     * Who is under this roof today.
     *
     * Everyone counts, not only the people who rotate: someone who belongs to
     * this home alone is simply here, and leaving them out would make a settled
     * household look empty.
     *
     * @return string[] names.
     */
    public function who_is_here( int $household_id ): array {
        $names = [];
        foreach ( $this->members_with_days( $household_id, current_time( 'Y-m-d' ), 1 ) as $member ) {
            if ( ! empty( $member['now']['is_here'] ) ) {
                $names[] = $member['name'];
            }
        }
        return $names;
    }

    /**
     * The short answer for the dashboard: who is where today, and the next
     * handover coming up.
     */
    public function get_whereabouts_summary( int $household_id ): array {
        $today = current_time( 'Y-m-d' );
        $members = $this->members_with_days( $household_id, $today, 28 );
        $handoffs = $this->collect_handoffs( $members, $household_id );

        $summary = [];
        foreach ( $members as $member ) {
            if ( ! $member['has_rotation'] ) {
                continue;
            }
            unset( $member['days'], $member['rotation'], $member['homes'] );
            $summary[] = $member;
        }

        return [
            'members'      => $summary,
            'next_handoff' => $handoffs ? $handoffs[0] : null,
        ];
    }

    /** @return array[] every member of the household, with their days filled in. */
    private function members_with_days( int $household_id, string $start, int $days ): array {
        $out = [];
        foreach ( $this->get_members( $household_id ) as $member ) {
            $out[] = $this->format_whereabouts_member( $member, $household_id, $start, $days );
        }
        return $out;
    }

    /** @param array $member A member as returned by format_member(). */
    private function format_whereabouts_member( array $member, int $household_id, string $start, int $days ): array {
        $user_id = (int) $member['id'];
        $rotation = Whereabouts::get_rotation( $user_id );
        $homes = Access::household_ids_for_user( $user_id );
        $names = $this->household_names( array_unique( array_merge( $homes, $rotation ? $rotation['homes'] : [] ) ) );

        $formatted = [
            'id'           => $user_id,
            'name'         => $member['name'],
            'role_label'   => $member['role_label'],
            'has_rotation' => (bool) $rotation,
            'can_rotate'   => count( $homes ) > 1,
            'rotation'     => $rotation ?: [],
            'homes'        => array_map( static function( int $id ) use ( $names ): array {
                return [ 'id' => $id, 'name' => $names[ $id ] ?? '' ];
            }, $homes ),
            'days'         => [],
        ];

        foreach ( Whereabouts::days_for_member( $user_id, $start, $days ) as $day ) {
            $formatted['days'][] = [
                'date'           => $day['date'],
                'household_id'   => $day['household_id'],
                'household_name' => $names[ $day['household_id'] ] ?? '',
                'is_here'        => $day['household_id'] === $household_id,
                'is_override'    => $day['is_override'],
            ];
        }

        $formatted['now'] = $this->format_current_stay( $user_id, $household_id, $homes, $names );

        return $formatted;
    }

    /**
     * Where a member is today and when that changes.
     *
     * Someone with no rotation who belongs to this household alone is simply
     * here; someone with several homes and no rotation could be anywhere, and
     * saying so is more use than guessing.
     */
    private function format_current_stay( int $user_id, int $household_id, array $homes, array $names ): array {
        $today = current_time( 'Y-m-d' );
        $where = Whereabouts::home_on( $user_id, $today );

        if ( ! $where['household_id'] ) {
            if ( count( $homes ) > 1 ) {
                return [];
            }
            return [
                'date'           => $today,
                'household_id'   => $household_id,
                'household_name' => $names[ $household_id ] ?? '',
                'is_here'        => true,
                'until'          => '',
                'next_name'      => '',
            ];
        }

        $ends = Whereabouts::stay_ends( $user_id, $today );

        return [
            'date'           => $today,
            'household_id'   => $where['household_id'],
            'household_name' => $names[ $where['household_id'] ] ?? '',
            'is_here'        => $where['household_id'] === $household_id,
            'until'          => $ends ? $ends['until'] : '',
            'next_name'      => $ends ? ( $names[ $ends['next_household_id'] ] ?? '' ) : '',
        ];
    }

    /**
     * Turn day-by-day whereabouts into handovers: people changing home on the
     * same day in the same direction are one handover, because that is one trip.
     *
     * @param array[] $members As built by format_whereabouts_member().
     */
    private function collect_handoffs( array $members, int $household_id ): array {
        $grouped = [];
        foreach ( $members as $member ) {
            $time = $member['rotation']['changeover_time'] ?? Whereabouts::DEFAULT_CHANGEOVER_TIME;
            $previous = null;
            foreach ( $member['days'] as $day ) {
                if ( null !== $previous && $day['household_id'] !== $previous['household_id'] ) {
                    $key = $day['date'] . ':' . $previous['household_id'] . ':' . $day['household_id'];
                    if ( ! isset( $grouped[ $key ] ) ) {
                        $grouped[ $key ] = [
                            'date'      => $day['date'],
                            'time'      => $time,
                            'from_id'   => $previous['household_id'],
                            'from_name' => $previous['household_name'],
                            'to_id'     => $day['household_id'],
                            'to_name'   => $day['household_name'],
                            'direction' => $previous['household_id'] === $household_id ? 'out' : ( $day['household_id'] === $household_id ? 'in' : 'elsewhere' ),
                            'members'   => [],
                        ];
                    }
                    $grouped[ $key ]['members'][] = $member['name'];
                }
                $previous = $day;
            }
        }

        $handoffs = array_values( $grouped );
        usort( $handoffs, static function( array $a, array $b ): int {
            return [ $a['date'], $a['time'] ] <=> [ $b['date'], $b['time'] ];
        } );

        return $handoffs;
    }

    /** Every household this one's members belong to, for the rotation form and the dial. */
    private function get_households_in_play( int $household_id ): array {
        $ids = [ $household_id ];
        foreach ( $this->get_members( $household_id ) as $member ) {
            $ids = array_merge( $ids, Access::household_ids_for_user( (int) $member['id'] ) );
        }
        $ids = array_unique( $ids );
        // A stable order keeps each home the same colour from one view to the next.
        sort( $ids );
        $out = [];
        foreach ( $this->household_names( $ids ) as $id => $name ) {
            $out[] = [ 'id' => $id, 'name' => $name ];
        }
        return $out;
    }

    /** @param int[] $household_ids @return array<int,string> */
    private function household_names( array $household_ids ): array {
        $names = [];
        foreach ( $household_ids as $id ) {
            $household = $this->format_household( (int) $id );
            if ( $household ) {
                $names[ (int) $id ] = $household['name'];
            }
        }
        return $names;
    }

    private function format_patterns(): array {
        $out = [];
        foreach ( Whereabouts::patterns() as $key => $pattern ) {
            $out[] = [
                'key'        => $key,
                'label'      => $pattern['label'],
                'start_hint' => $pattern['start_hint'],
                'cycle'      => $pattern['cycle'],
                'homes'      => $pattern['homes'],
            ];
        }
        return $out;
    }

    /* ---------------------------------------------------------------- House info */

    public const META_INFO = '_households_info';

    /**
     * The things about a house that everyone ends up asking: the wifi code,
     * where the water main valve is, which day the bins go out.
     *
     * It belongs to the household rather than to a member, because it is true
     * of the place. Everyone who belongs there can read it — that is the point
     * of writing it down — so it is not the place for anything that should not
     * be shared with the whole household.
     *
     * @return array[] each with a label and a detail.
     */
    public function get_household_info( int $household_id ): array {
        $stored = get_post_meta( $household_id, self::META_INFO, true );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $info = [];
        foreach ( $stored as $entry ) {
            $label = isset( $entry['label'] ) ? trim( (string) $entry['label'] ) : '';
            if ( '' === $label ) {
                continue;
            }
            $info[] = [
                'label'  => $label,
                'detail' => isset( $entry['detail'] ) ? (string) $entry['detail'] : '',
            ];
        }
        return $info;
    }

    public function add_household_info( int $household_id, string $label, string $detail ): bool {
        $label = sanitize_text_field( $label );
        if ( '' === trim( $label ) ) {
            return false;
        }
        $info = $this->get_household_info( $household_id );
        $info[] = [ 'label' => $label, 'detail' => sanitize_textarea_field( $detail ) ];
        update_post_meta( $household_id, self::META_INFO, $info );
        return true;
    }

    public function remove_household_info( int $household_id, int $index ): void {
        $info = $this->get_household_info( $household_id );
        if ( ! isset( $info[ $index ] ) ) {
            return;
        }
        unset( $info[ $index ] );
        update_post_meta( $household_id, self::META_INFO, array_values( $info ) );
    }
    /* ---------------------------------------------------------------- Tasks */

    public function get_tasks( int $household_id ): array {
        $tasks = array_map( [ $this, 'format_task' ], $this->get_related_posts( $household_id, 'household_task' ) );
        usort( $tasks, static function( array $a, array $b ): int {
            if ( $a['is_done'] !== $b['is_done'] ) {
                return (int) $a['is_done'] <=> (int) $b['is_done'];
            }
            return strcmp( $a['due_date'] ?: '9999-12-31', $b['due_date'] ?: '9999-12-31' );
        } );
        return $tasks;
    }

    public function add_task( int $household_id, string $title, int $member_id = 0, string $task_type = 'task', string $due_date = '' ): int {
        $member_id = $this->normalize_member_id( $household_id, $member_id );
        $task_id = wp_insert_post( [
            'post_author' => $this->get_household_owner_user_id( $household_id ),
            'post_parent' => $household_id,
            'post_status' => 'private',
            'post_title'  => $title,
            'post_type'   => 'household_task',
        ], true );

        if ( is_wp_error( $task_id ) ) {
            return 0;
        }

        update_post_meta( $task_id, self::META_HOUSEHOLD_ID, $household_id );
        update_post_meta( $task_id, '_households_task_type', in_array( $task_type, [ 'task', 'appointment' ], true ) ? $task_type : 'task' );
        update_post_meta( $task_id, '_households_due_date', $this->normalize_date( $due_date ) );
        update_post_meta( $task_id, '_households_is_done', 0 );
        $this->assign_member( $task_id, $member_id );

        return (int) $task_id;
    }

    /**
     * @param int $actor_id User doing the toggling; recorded for the audit trail
     *                      when a manager acts on someone else's behalf.
     */
    public function toggle_task( int $household_id, int $task_id, int $actor_id = 0 ): bool {
        $task = get_post( $task_id );
        if ( ! $task || 'household_task' !== $task->post_type || (int) get_post_meta( $task_id, self::META_HOUSEHOLD_ID, true ) !== $household_id ) {
            return false;
        }

        $is_done = (int) get_post_meta( $task_id, '_households_is_done', true ) ? 0 : 1;
        update_post_meta( $task_id, '_households_is_done', $is_done );
        update_post_meta( $task_id, '_households_completed_at', $is_done ? current_time( 'mysql' ) : '' );
        update_post_meta( $task_id, '_households_completed_by', $is_done ? $actor_id : 0 );

        return true;
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
        if ( ! $household || 'household' !== $household->post_type ) {
            return [];
        }
        return [
            'id'         => $household_id,
            'name'       => $household->post_title,
            'created_at' => $household->post_date,
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
            'task_type'    => get_post_meta( $task_id, '_households_task_type', true ) ?: 'task',
            'due_date'     => get_post_meta( $task_id, '_households_due_date', true ),
            'is_done'      => (string) (int) get_post_meta( $task_id, '_households_is_done', true ),
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
