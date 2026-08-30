<?php

namespace Households;

/**
 * Persistence.
 *
 * A home is a term in the `household` taxonomy: its name is the term name, its
 * administrators are term meta. Everything that happens in a home — the people,
 * the facts about the place, the things kept there, the tasks — is a post
 * tagged with that term, so one join answers every question, and a question
 * about several homes at once is the same join with more terms in it.
 *
 * A person is a post whose author is their WordPress user, or nobody at all.
 * What is true of them in prose — sizes, allergies, what the next person needs
 * to know — is the post's own content, so its history is the post's revisions.
 */
class Storage {
    public const WP_ROLE = 'households_member';

    public const FACT = 'household_fact';
    public const ITEM = 'household_item';
    public const TASK = 'household_task';

    /** Person meta. */
    public const META_LABEL     = '_households_label';
    public const META_BIRTHDATE = '_households_birthdate';
    public const META_LAST_HOME = '_households_last_home';

    /** Task meta. */
    public const META_TASK_TYPE = '_households_task_type';
    public const META_DUE_DATE  = '_households_due_date';
    public const META_DONE_AT   = '_households_done_at';

    /* ---------------------------------------------------------------- Homes */

    public function create_home( string $name, int $admin_user_id = 0 ): int {
        $name = sanitize_text_field( $name );
        if ( '' === trim( $name ) ) {
            return 0;
        }
        $term = wp_insert_term( $name, Access::TAXONOMY );
        if ( is_wp_error( $term ) ) {
            return 0;
        }
        $home_id = (int) $term['term_id'];
        if ( $admin_user_id ) {
            Access::set_admin( $home_id, $admin_user_id, true );
        }
        return $home_id;
    }

    public function update_home( int $home_id, string $name ): void {
        $name = sanitize_text_field( $name );
        if ( '' !== trim( $name ) ) {
            wp_update_term( $home_id, Access::TAXONOMY, [ 'name' => $name ] );
        }
    }

    /** @return array{id:int,name:string}|array{} */
    public function get_home( int $home_id ): array {
        $term = $home_id ? get_term( $home_id, Access::TAXONOMY ) : null;
        if ( ! $term || is_wp_error( $term ) ) {
            return [];
        }
        return [
            'id'   => (int) $term->term_id,
            'name' => $term->name,
        ];
    }

    /** @return array[] the homes this person belongs to, named and ordered. */
    public function get_homes_for_person( int $person_id ): array {
        $homes = array_filter( array_map( [ $this, 'get_home' ], Access::home_ids_for_person( $person_id ) ) );
        usort( $homes, static function( array $a, array $b ): int {
            return strcasecmp( $a['name'], $b['name'] );
        } );
        return array_values( $homes );
    }

    /**
     * The index: every home the viewer belongs to, with who is under each roof
     * today and what is still open there.
     */
    public function get_homes_overview( int $user_id ): array {
        $person_id = Access::person_for_user( $user_id );
        $overview = [];
        foreach ( $this->get_homes_for_person( $person_id ) as $home ) {
            $open = 0;
            foreach ( $this->get_tasks( $home['id'] ) as $task ) {
                $open += $task['is_done'] ? 0 : 1;
            }
            $overview[] = [
                'id'         => $home['id'],
                'name'       => $home['name'],
                'here'       => $this->who_is_here( $home['id'] ),
                'open_tasks' => $open,
                'can_manage' => Access::can_manage( $user_id, $home['id'] ),
            ];
        }
        return $overview;
    }

    /**
     * The last home this user was looking at.
     *
     * Every home has its own URL, so this is not a mode anyone switches into:
     * it is a note of where they were, used to pick a landing spot and to give
     * the cross-home views somewhere to look from.
     */
    public function last_home_id( int $user_id ): int {
        $person_id = Access::person_for_user( $user_id );
        $homes = Access::home_ids_for_person( $person_id );
        if ( ! $homes ) {
            return 0;
        }
        $last = (int) get_post_meta( $person_id, self::META_LAST_HOME, true );
        return in_array( $last, $homes, true ) ? $last : $homes[0];
    }

    public function remember_home( int $user_id, int $home_id ): bool {
        $person_id = Access::person_for_user( $user_id );
        if ( ! Access::is_member( $person_id, $home_id ) ) {
            return false;
        }
        update_post_meta( $person_id, self::META_LAST_HOME, $home_id );
        return true;
    }

    /* ---------------------------------------------------------------- People */

    /**
     * Add a person to a home.
     *
     * An account is only created when there is an email to create it from.
     * Without one the record still stands on its own — which is how a toddler
     * whose shoe size is worth writing down, or a relative who will never log
     * in, gets to exist here without a login nobody would use.
     */
    public function add_person( int $home_id, string $name, array $args = [] ): int {
        $name = sanitize_text_field( $name );
        if ( '' === trim( $name ) || ! $this->get_home( $home_id ) ) {
            return 0;
        }

        $email = isset( $args['email'] ) ? sanitize_email( (string) $args['email'] ) : '';
        $user_id = $email ? $this->user_for_email( $email, $name, (string) ( $args['password'] ?? '' ) ) : 0;

        $person_id = $user_id ? Access::person_for_user( $user_id ) : 0;
        if ( ! $person_id ) {
            $person_id = (int) wp_insert_post( [
                'post_author' => $user_id,
                'post_status' => 'private',
                'post_title'  => $name,
                'post_type'   => Access::PERSON,
            ] );
        }
        if ( ! $person_id ) {
            return 0;
        }

        Access::flush_person_cache();
        Access::join( $person_id, $home_id );
        $this->save_person( $person_id, $args );

        return $person_id;
    }

    /** Link an existing account by email, or make one that can only open the app. */
    private function user_for_email( string $email, string $name, string $password ): int {
        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            return (int) $existing->ID;
        }
        $user_id = wp_insert_user( [
            'display_name' => $name,
            'first_name'   => $name,
            'role'         => self::WP_ROLE,
            'user_email'   => $email,
            'user_login'   => $this->unique_login( $name ),
            'user_pass'    => '' !== $password ? $password : wp_generate_password( 20 ),
        ] );
        return is_wp_error( $user_id ) ? 0 : (int) $user_id;
    }

    private function unique_login( string $name ): string {
        $base = sanitize_user( strtolower( str_replace( ' ', '', $name ) ), true ) ?: 'member';
        $login = $base;
        $suffix = 2;
        while ( username_exists( $login ) ) {
            $login = $base . $suffix++;
        }
        return $login;
    }

    /** @return array The person as the app talks about them, or an empty array. */
    public function get_person( int $person_id ): array {
        $person = get_post( $person_id );
        if ( ! $person || Access::PERSON !== $person->post_type ) {
            return [];
        }
        $birthdate = (string) get_post_meta( $person_id, self::META_BIRTHDATE, true );
        return [
            'id'        => (int) $person->ID,
            'name'      => $person->post_title,
            'about'     => $person->post_content,
            'label'     => (string) get_post_meta( $person_id, self::META_LABEL, true ),
            'is_child'  => Access::is_child( $person_id ),
            'birthdate' => $birthdate,
            'age'       => $this->age_from_birthdate( $birthdate ),
            'user_id'   => (int) $person->post_author,
            'homes'     => $this->get_homes_for_person( $person_id ),
        ];
    }

    /** @return array[] everyone tagged with this home. */
    public function get_people( int $home_id ): array {
        return array_values( array_filter( array_map( [ $this, 'get_person' ], Access::person_ids_in_home( $home_id ) ) ) );
    }

    /**
     * Save what is structured about a person. Everything else they are is prose
     * in `about`, which is the post content, so editing it leaves a revision.
     */
    public function save_person( int $person_id, array $fields ): void {
        if ( ! Access::is_person( $person_id ) ) {
            return;
        }
        if ( array_key_exists( 'name', $fields ) && '' !== trim( (string) $fields['name'] ) ) {
            wp_update_post( [
                'ID'         => $person_id,
                'post_title' => sanitize_text_field( (string) $fields['name'] ),
            ] );
        }
        if ( array_key_exists( 'about', $fields ) ) {
            wp_update_post( [
                'ID'           => $person_id,
                'post_content' => wp_kses_post( (string) $fields['about'] ),
            ] );
        }
        if ( array_key_exists( 'label', $fields ) ) {
            update_post_meta( $person_id, self::META_LABEL, sanitize_text_field( (string) $fields['label'] ) );
        }
        if ( array_key_exists( 'is_child', $fields ) ) {
            update_post_meta( $person_id, Access::META_IS_CHILD, $fields['is_child'] ? 1 : 0 );
        }
        if ( array_key_exists( 'birthdate', $fields ) ) {
            update_post_meta( $person_id, self::META_BIRTHDATE, $this->normalize_date( (string) $fields['birthdate'] ) );
        }
    }

    /** People with a birthdate, ordered by how soon their next birthday is. */
    public function get_upcoming_birthdays( int $home_id ): array {
        $today = new \DateTimeImmutable( current_time( 'Y-m-d' ) );
        $list = [];
        foreach ( $this->get_people( $home_id ) as $person ) {
            $born = '' !== $person['birthdate'] ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $person['birthdate'] ) : null;
            if ( ! $born ) {
                continue;
            }
            $next = $born->setDate( (int) $today->format( 'Y' ), (int) $born->format( 'm' ), (int) $born->format( 'd' ) );
            if ( $next < $today ) {
                $next = $next->modify( '+1 year' );
            }
            $list[] = [
                'id'         => $person['id'],
                'name'       => $person['name'],
                'date'       => $next->format( 'Y-m-d' ),
                'days_until' => (int) $today->diff( $next )->days,
                'turning'    => (int) $next->format( 'Y' ) - (int) $born->format( 'Y' ),
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

    /* ---------------------------------------------------------------- Facts and items */

    /**
     * Facts and items are the same record in two moods: a title, some detail,
     * and the home it is true of. A fact is what the house needs you to know —
     * the wifi network, where the water main valve is, which day the bins go
     * out. An item is a thing kept there. Both keep their history, because both
     * are posts and both are edited in place rather than replaced.
     */
    public function add_note( int $home_id, string $post_type, string $title, string $detail ): int {
        $title = sanitize_text_field( $title );
        if ( '' === trim( $title ) || ! $this->get_home( $home_id ) ) {
            return 0;
        }
        $post_id = (int) wp_insert_post( [
            'post_content' => wp_kses_post( $detail ),
            'post_status'  => 'private',
            'post_title'   => $title,
            'post_type'    => $post_type,
        ] );
        if ( $post_id ) {
            wp_set_object_terms( $post_id, [ $home_id ], Access::TAXONOMY );
        }
        return $post_id;
    }

    public function update_note( int $home_id, string $post_type, int $post_id, string $title, string $detail ): bool {
        if ( ! $this->note_belongs_to( $post_id, $post_type, $home_id ) || '' === trim( $title ) ) {
            return false;
        }
        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => wp_kses_post( $detail ),
            'post_title'   => sanitize_text_field( $title ),
        ] );
        return true;
    }

    public function remove_note( int $home_id, string $post_type, int $post_id ): bool {
        if ( ! $this->note_belongs_to( $post_id, $post_type, $home_id ) ) {
            return false;
        }
        wp_trash_post( $post_id );
        return true;
    }

    /** @return array[] every note of this type in this home. */
    public function get_notes( int $home_id, string $post_type ): array {
        $notes = [];
        foreach ( $this->posts_in_home( $home_id, $post_type ) as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }
            $notes[] = [
                'id'       => (int) $post->ID,
                'title'    => $post->post_title,
                'detail'   => $post->post_content,
                'modified' => $post->post_modified,
            ];
        }
        return $notes;
    }

    private function note_belongs_to( int $post_id, string $post_type, int $home_id ): bool {
        $post = get_post( $post_id );
        return $post && $post_type === $post->post_type && in_array( $home_id, $this->home_ids_of_post( $post_id ), true );
    }

    /* ---------------------------------------------------------------- Tasks */

    /**
     * A task belongs to a home, and either to one person in it or to the house
     * as a whole. The person is the post's parent, so "everything assigned to
     * her" is one indexed lookup rather than a search.
     */
    public function add_task( int $home_id, string $title, int $person_id = 0, string $task_type = 'task', string $due_date = '' ): int {
        $title = sanitize_text_field( $title );
        if ( '' === trim( $title ) || ! $this->get_home( $home_id ) ) {
            return 0;
        }
        if ( $person_id && ! Access::is_member( $person_id, $home_id ) ) {
            $person_id = 0;
        }
        $task_id = (int) wp_insert_post( [
            'post_parent' => $person_id,
            'post_status' => 'private',
            'post_title'  => $title,
            'post_type'   => self::TASK,
        ] );
        if ( ! $task_id ) {
            return 0;
        }
        wp_set_object_terms( $task_id, [ $home_id ], Access::TAXONOMY );
        update_post_meta( $task_id, self::META_TASK_TYPE, in_array( $task_type, [ 'task', 'appointment' ], true ) ? $task_type : 'task' );
        update_post_meta( $task_id, self::META_DUE_DATE, $this->normalize_date( $due_date ) );
        return $task_id;
    }

    /** @return array[] open first, then by due date. */
    public function get_tasks( int $home_id ): array {
        $tasks = [];
        foreach ( $this->posts_in_home( $home_id, self::TASK ) as $task_id ) {
            $post = get_post( $task_id );
            if ( ! $post ) {
                continue;
            }
            $person_id = (int) $post->post_parent;
            $person = $person_id ? get_post( $person_id ) : null;
            $tasks[] = [
                'id'        => (int) $post->ID,
                'title'     => $post->post_title,
                'person_id' => $person_id,
                'person'    => $person ? $person->post_title : '',
                'task_type' => get_post_meta( $post->ID, self::META_TASK_TYPE, true ) ?: 'task',
                'due_date'  => (string) get_post_meta( $post->ID, self::META_DUE_DATE, true ),
                'is_done'   => '' !== (string) get_post_meta( $post->ID, self::META_DONE_AT, true ),
            ];
        }
        usort( $tasks, static function( array $a, array $b ): int {
            if ( $a['is_done'] !== $b['is_done'] ) {
                return (int) $a['is_done'] <=> (int) $b['is_done'];
            }
            return strcmp( $a['due_date'] ?: '9999-12-31', $b['due_date'] ?: '9999-12-31' );
        } );
        return $tasks;
    }

    public function toggle_task( int $home_id, int $task_id ): bool {
        $post = get_post( $task_id );
        if ( ! $post || self::TASK !== $post->post_type || ! in_array( $home_id, $this->home_ids_of_post( $task_id ), true ) ) {
            return false;
        }
        $done = '' !== (string) get_post_meta( $task_id, self::META_DONE_AT, true );
        if ( $done ) {
            delete_post_meta( $task_id, self::META_DONE_AT );
        } else {
            update_post_meta( $task_id, self::META_DONE_AT, current_time( 'mysql' ) );
        }
        return true;
    }

    public function remove_task( int $home_id, int $task_id ): bool {
        $post = get_post( $task_id );
        if ( ! $post || self::TASK !== $post->post_type || ! in_array( $home_id, $this->home_ids_of_post( $task_id ), true ) ) {
            return false;
        }
        wp_trash_post( $task_id );
        return true;
    }

    /* ---------------------------------------------------------------- Dashboard */

    /**
     * Everything one home's page shows, seen by `$user_id`, about `$person_id`.
     *
     * Someone organising sees the whole house. Anyone else sees what is theirs
     * and what belongs to everyone, which is the same rule whether they are a
     * child looking at their own page or a parent viewing it as them.
     */
    public function get_dashboard( int $user_id, int $home_id, int $person_id = 0 ): array {
        $home = $this->get_home( $home_id );
        $viewer_person = Access::person_for_user( $user_id );
        if ( ! $home || ! Access::is_member( $viewer_person, $home_id ) ) {
            return [];
        }

        $person_id = $person_id ?: $viewer_person;
        if ( ! Access::is_member( $person_id, $home_id ) ) {
            $person_id = $viewer_person;
        }

        $can_organise = Access::can_organise( $user_id, $home_id );
        $subject_organises = ! Access::is_child( $person_id );

        $tasks = array_values( array_filter( $this->get_tasks( $home_id ), static function( array $task ) use ( $person_id, $subject_organises ): bool {
            return $subject_organises || ! $task['person_id'] || $task['person_id'] === $person_id;
        } ) );

        // Administrators are stored as user IDs; the page talks about people.
        $admins = array_values( array_filter( array_map(
            [ Access::class, 'person_for_user' ],
            Access::admins_of( $home_id )
        ) ) );

        return [
            'home'       => $home,
            'homes'      => $this->get_homes_for_person( $viewer_person ),
            'people'     => $this->get_people( $home_id ),
            'admins'     => $admins,
            'subject'    => $this->get_person( $person_id ),
            'tasks'      => $tasks,
            'facts'      => $this->get_notes( $home_id, self::FACT ),
            'items'      => $this->get_notes( $home_id, self::ITEM ),
            'here'       => $this->who_is_here( $home_id ),
            'birthdays'  => $this->get_upcoming_birthdays( $home_id ),
            'viewer'     => [
                'user_id'      => $user_id,
                'person_id'    => $viewer_person,
                'can_manage'   => Access::can_manage( $user_id, $home_id ),
                'can_organise' => $can_organise,
                'viewing_as'   => $person_id !== $viewer_person,
            ],
        ];
    }

    /* ---------------------------------------------------------------- Whereabouts */

    /** Who is under this roof today: everyone whose rotation says so, plus everyone who does not rotate. */
    public function who_is_here( int $home_id ): array {
        $today = current_time( 'Y-m-d' );
        $here = [];
        foreach ( $this->get_people( $home_id ) as $person ) {
            $where = Whereabouts::home_on( $person['id'], $today );
            if ( ! $where['home_id'] || $where['home_id'] === $home_id ) {
                $here[] = [
                    'id'       => $person['id'],
                    'name'     => $person['name'],
                    'rotates'  => (bool) $where['home_id'],
                    'is_child' => $person['is_child'],
                ];
            }
        }
        return $here;
    }

    /**
     * The board: a day-by-day grid of which home each person is at, and the
     * handovers those days imply.
     *
     * Everything is expressed from `$home_id`'s point of view — `is_here` is
     * what someone standing in this kitchen wants to know.
     */
    public function get_whereabouts_board( int $home_id, string $start = '', int $days = 14 ): array {
        $days = max( 1, min( 56, $days ) );
        $start = $this->normalize_date( $start ) ?: current_time( 'Y-m-d' );
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

        $people = [];
        foreach ( $this->get_people( $home_id ) as $person ) {
            $rotation = Whereabouts::get_rotation( $person['id'] );
            $days_out = [];
            foreach ( Whereabouts::days_for_person( $person['id'], $start, $days ) as $day ) {
                $home = $this->get_home( $day['home_id'] );
                $days_out[] = [
                    'date'        => $day['date'],
                    'home_id'     => $day['home_id'],
                    'home_name'   => $home['name'] ?? '',
                    'is_here'     => $day['home_id'] === $home_id,
                    'is_override' => $day['is_override'],
                ];
            }
            $people[] = [
                'id'       => $person['id'],
                'name'     => $person['name'],
                'is_child' => $person['is_child'],
                'rotation' => $rotation,
                'can_rotate' => Whereabouts::can_rotate( $person['id'] ),
                'days'     => $days_out,
                'homes'    => $person['homes'],
            ];
        }

        return [
            'home'      => $this->get_home( $home_id ),
            'dates'     => $dates,
            'people'    => $people,
            'handovers' => $this->collect_handovers( $people, $home_id ),
            'patterns'  => $this->format_patterns(),
            'start'     => $start,
            'days'      => $days,
        ];
    }

    /**
     * The handovers the board implies.
     *
     * People moving between the same two homes on the same day are one
     * handover, because that is one trip.
     */
    private function collect_handovers( array $people, int $home_id ): array {
        $handovers = [];
        foreach ( $people as $person ) {
            $previous = null;
            foreach ( $person['days'] as $day ) {
                if ( null !== $previous && $day['home_id'] !== $previous['home_id'] ) {
                    $key = $day['date'] . ':' . $previous['home_id'] . ':' . $day['home_id'];
                    if ( ! isset( $handovers[ $key ] ) ) {
                        $handovers[ $key ] = [
                            'date'      => $day['date'],
                            'from_id'   => $previous['home_id'],
                            'from_name' => $previous['home_name'],
                            'to_id'     => $day['home_id'],
                            'to_name'   => $day['home_name'],
                            'direction' => $previous['home_id'] === $home_id ? 'out' : ( $day['home_id'] === $home_id ? 'in' : 'elsewhere' ),
                            'people'    => [],
                        ];
                    }
                    $handovers[ $key ]['people'][] = $person['name'];
                }
                $previous = $day;
            }
        }
        usort( $handovers, static function( array $a, array $b ): int {
            return strcmp( $a['date'], $b['date'] );
        } );
        return array_values( $handovers );
    }

    private function format_patterns(): array {
        $patterns = [];
        foreach ( Whereabouts::patterns() as $key => $pattern ) {
            $patterns[] = [
                'key'        => $key,
                'label'      => $pattern['label'],
                'cycle'      => $pattern['cycle'],
                'start_hint' => $pattern['start_hint'],
                'homes'      => $pattern['homes'],
            ];
        }
        return $patterns;
    }

    /* ---------------------------------------------------------------- Helpers */

    /** @return int[] post IDs of this type tagged with this home, oldest first. */
    private function posts_in_home( int $home_id, string $post_type ): array {
        if ( ! $home_id ) {
            return [];
        }
        return array_map( 'intval', get_posts( [
            'fields'           => 'ids',
            'numberposts'      => -1,
            'order'            => 'ASC',
            'orderby'          => 'ID',
            'post_status'      => 'private',
            'post_type'        => $post_type,
            'suppress_filters' => false,
            'tax_query'        => [
                [
                    'field'    => 'term_id',
                    'taxonomy' => Access::TAXONOMY,
                    'terms'    => $home_id,
                ],
            ],
        ] ) );
    }

    /** @return int[] the homes a post is tagged with. */
    private function home_ids_of_post( int $post_id ): array {
        $terms = wp_get_object_terms( $post_id, Access::TAXONOMY, [ 'fields' => 'ids' ] );
        return is_wp_error( $terms ) ? [] : array_map( 'intval', $terms );
    }

    private function normalize_date( string $date ): string {
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
    }
}
