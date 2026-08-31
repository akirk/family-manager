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

        // Terms are global; the names families give their homes are not. Two
        // households on one site may both have a "Home", and neither should be
        // refused — or told the other exists. Only the slug has to be unique,
        // so it is made unique here and the name is left alone.
        $slug = wp_unique_term_slug( sanitize_title( $name ), (object) [
            'taxonomy'   => Access::TAXONOMY,
            'parent'     => 0,
            'term_id'    => 0,
            'term_group' => 0,
        ] );
        $term = wp_insert_term( $name, Access::TAXONOMY, $slug ? [ 'slug' => $slug ] : [] );
        if ( is_wp_error( $term ) ) {
            return 0;
        }
        $home_id = (int) $term['term_id'];
        if ( $admin_user_id ) {
            Access::set_admin( $home_id, $admin_user_id, true );
        }
        return $home_id;
    }

    /**
     * Start a home. Whoever starts it administers it, and nothing else follows.
     *
     * Setting a household up is not the same as living in it: somebody has to
     * make the grandparents' house, or the flat a child stays in every other
     * weekend, before anybody is put in it. So this settles the one thing a
     * home cannot be without — someone who may add to it — and leaves who is
     * in it to be said next, on its own page, the starter included.
     */
    public function start_home( int $user_id, string $name ): int {
        return $this->create_home( $name, $user_id );
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

    /** @return array[] the homes this user may open, named and ordered. */
    public function get_homes_for_user( int $user_id ): array {
        $homes = array_filter( array_map( [ $this, 'get_home' ], Access::home_ids_for_user( $user_id ) ) );
        usort( $homes, static function( array $a, array $b ): int {
            return strcasecmp( $a['name'], $b['name'] );
        } );
        return array_values( $homes );
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
        $overview = [];
        foreach ( $this->get_homes_for_user( $user_id ) as $home ) {
            $open = 0;
            foreach ( $this->get_tasks( $home['id'] ) as $task ) {
                $open += $task['is_done'] ? 0 : 1;
            }
            $overview[] = [
                'id'         => $home['id'],
                'name'       => $home['name'],
                'here'       => $this->who_is_here( $home['id'] ),
                'unknown'    => $this->whereabouts_unknown( $home['id'] ),
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
        $homes = Access::home_ids_for_user( $user_id );
        if ( ! $homes ) {
            return 0;
        }
        // The note is kept on the person, so someone who only administers has
        // nowhere to keep one and simply lands on the first of theirs.
        $person_id = Access::person_for_user( $user_id );
        $last = $person_id ? (int) get_post_meta( $person_id, self::META_LAST_HOME, true ) : 0;
        return in_array( $last, $homes, true ) ? $last : $homes[0];
    }

    public function remember_home( int $user_id, int $home_id ): bool {
        $person_id = Access::person_for_user( $user_id );
        if ( ! $person_id || ! Access::can_reach( $user_id, $home_id ) ) {
            return false;
        }
        update_post_meta( $person_id, self::META_LAST_HOME, $home_id );
        return true;
    }

    /* ---------------------------------------------------------------- People */

    /**
     * Put the person behind an account into a home, making them a record first
     * if they have never needed one.
     *
     * Whoever starts a household is outside it until they say otherwise, and
     * this is how they say it. Someone who already has a record joins with it:
     * a second household does not make a second them.
     */
    public function add_self( int $user_id, int $home_id ): int {
        $person_id = Access::person_for_user( $user_id );
        if ( $person_id ) {
            Access::join( $person_id, $home_id );
            return $person_id;
        }

        $user = get_userdata( $user_id );
        $person_id = $this->add_person( $home_id, $user ? $user->display_name : '' );
        Access::assign_user( $person_id, $user_id );
        return $person_id;
    }

    /**
     * Add a person to a home: a name, and whatever else is known about them.
     *
     * Only the record is made here. Whether anybody signs in as them is a
     * separate question with a separate answer — `Access::assign_user()` — and
     * most of the time it is no. A toddler whose shoe size is worth writing
     * down, or a relative you only keep notes about, is a person here without
     * ever being a login.
     */
    public function add_person( int $home_id, string $name, array $args = [] ): int {
        $name = sanitize_text_field( $name );
        if ( '' === trim( $name ) || ! $this->get_home( $home_id ) ) {
            return 0;
        }

        $person_id = (int) wp_insert_post( [
            'post_author' => 0,
            'post_status' => 'private',
            'post_title'  => $name,
            'post_type'   => Access::PERSON,
        ] );
        if ( ! $person_id ) {
            return 0;
        }

        Access::join( $person_id, $home_id );
        $this->save_person( $person_id, $args );

        return $person_id;
    }

    /**
     * Accounts that could be this person's: the ones nobody else answers for.
     *
     * @return array[] id and a label saying who the account is, ordered by name.
     */
    public function assignable_users( int $person_id ): array {
        $users = [];
        foreach ( get_users( [ 'orderby' => 'display_name' ] ) as $user ) {
            $taken = Access::person_for_user( (int) $user->ID );
            if ( $taken && $taken !== $person_id ) {
                continue;
            }
            $users[] = [
                'id'    => (int) $user->ID,
                'name'  => $user->display_name ? $user->display_name : $user->user_login,
                'login' => $user->user_login,
            ];
        }
        return $users;
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

    /**
     * Everyone across every home the viewer belongs to, each listed once.
     *
     * Someone in three of your homes is one person, not three entries — which
     * is the whole reason a person is a record rather than a membership row.
     */
    public function get_people_overview( int $user_id ): array {
        $viewer = Access::person_for_user( $user_id );
        $people = [];
        foreach ( Access::home_ids_for_user( $user_id ) as $home_id ) {
            foreach ( Access::person_ids_in_home( $home_id ) as $person_id ) {
                if ( isset( $people[ $person_id ] ) ) {
                    continue;
                }
                $person = $this->get_person( $person_id );
                if ( ! $person ) {
                    continue;
                }
                // Both come out of the same lookup: where they are today, and
                // whether that is something said about the day rather than
                // something a pattern worked out. The second is what decides
                // whether there is anything to take back.
                $at = Whereabouts::home_on( $person_id, current_time( 'Y-m-d' ) );
                $at_home = $at['home_id'] ? $this->get_home( $at['home_id'] ) : [];
                $person['location'] = [
                    'home_id' => $at['home_id'],
                    'name'    => isset( $at_home['name'] ) ? $at_home['name'] : '',
                    'known'   => (bool) $at['home_id'],
                ];
                $person['said'] = $at['is_override'];
                $person['rotates'] = (bool) Whereabouts::get_rotation( $person_id );
                $person['is_you'] = $person_id === $viewer;
                $people[ $person_id ] = $person;
            }
        }
        $people = array_values( $people );
        usort( $people, static function( array $a, array $b ): int {
            return strcasecmp( $a['name'], $b['name'] );
        } );
        return $people;
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

    /**
     * Move a thing to another home.
     *
     * The term is replaced rather than added: a thing is in one place at a
     * time, which is the whole point of writing down where it is.
     */
    public function move_note( int $home_id, string $post_type, int $post_id, int $target_home_id ): bool {
        if ( ! $this->note_belongs_to( $post_id, $post_type, $home_id ) || ! $this->get_home( $target_home_id ) ) {
            return false;
        }
        wp_set_object_terms( $post_id, [ $target_home_id ], Access::TAXONOMY );
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

    /**
     * Everything kept across the homes the viewer belongs to, and which home it
     * is at. A thing is in one place at a time, so this is a list, not a join.
     */
    public function get_things_overview( int $user_id ): array {
        $things = [];
        foreach ( Access::home_ids_for_user( $user_id ) as $home_id ) {
            $home = $this->get_home( $home_id );
            foreach ( $this->get_notes( $home_id, self::ITEM ) as $thing ) {
                $thing['home_id'] = $home_id;
                $thing['home_name'] = $home['name'] ?? '';
                $things[] = $thing;
            }
        }
        usort( $things, static function( array $a, array $b ): int {
            return strcasecmp( $a['title'], $b['title'] );
        } );
        return $things;
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
        if ( ! $home || ! Access::can_reach( $user_id, $home_id ) ) {
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
            'homes'      => $this->get_homes_for_user( $user_id ),
            'people'     => $this->get_people( $home_id ),
            'admins'     => $admins,
            'subject'    => $this->get_person( $person_id ),
            'tasks'      => $tasks,
            'facts'      => $this->get_notes( $home_id, self::FACT ),
            'items'      => $this->get_notes( $home_id, self::ITEM ),
            'here'       => $this->who_is_here( $home_id ),
            'unknown'    => $this->whereabouts_unknown( $home_id ),
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

    /* ---------------------------------------------------------------- Your day */

    /** How far ahead the dashboard looks: a fortnight, like the board. */
    private const AGENDA_DAYS = 14;

    /**
     * The index, which is about you rather than about your homes.
     *
     * Where you are today, what is yours to do wherever it happens to be
     * written down, and what the fortnight ahead holds. The appointments, the
     * moves and the birthdays are one dated list rather than three, because a
     * day is one thing to the person living it even when it is spread across
     * three houses.
     */
    public function get_my_day( int $user_id ): array {
        $viewer = Access::person_for_user( $user_id );
        $today = current_time( 'Y-m-d' );
        $where = $this->where_person_is( $viewer, $today );

        return [
            'person' => $this->get_person( $viewer ),
            'today'  => $today,
            'where'  => $where,
            // The household you are standing in, read exactly as its own page
            // reads it: what is written down there, what is kept there, and who
            // is in it. Not knowing where you are, there is nothing to read.
            'here'   => $where['home_id'] ? $this->get_dashboard( $user_id, $where['home_id'] ) : [],
            // Who could go with you, so the page can offer them by name.
            'party'  => $this->people_you_can_place( $user_id ),
            'agenda' => $this->get_agenda( $user_id, $viewer, $today, self::AGENDA_DAYS ),
            'homes'  => $this->get_homes_overview( $user_id ),
        ];
    }

    /**
     * Everyone this viewer may say a day for: themselves, and anyone in a
     * household they organise. Each says where they are today, which is how the
     * page can mark the ones already under the roof being asked about.
     *
     * @return array[] people as `get_people_overview` says them.
     */
    public function people_you_can_place( int $user_id ): array {
        $people = [];
        foreach ( $this->get_people_overview( $user_id ) as $person ) {
            if ( Access::can_place_person( $user_id, $person['id'] ) ) {
                $people[] = $person;
            }
        }
        return $people;
    }

    /**
     * Where a person is today, who is under that roof with them, and — if they
     * rotate — when that stops being true.
     */
    private function where_person_is( int $person_id, string $today ): array {
        $where = $this->location_today( $person_id );
        $stay = Whereabouts::stay_ends( $person_id, $today );
        $next = $stay ? $this->get_home( $stay['next_home_id'] ) : [];
        $said = Whereabouts::home_on( $person_id, $today )['is_override'];

        $with = [];
        foreach ( $where['home_id'] ? $this->who_is_here( $where['home_id'] ) : [] as $person ) {
            if ( $person['id'] !== $person_id ) {
                $with[] = $person['name'];
            }
        }

        return [
            'home_id'     => $where['home_id'],
            'name'        => $where['name'],
            'known'       => $where['known'],
            'rotates'     => (bool) Whereabouts::get_rotation( $person_id ),
            // Whether today is something you said, as opposed to something a
            // pattern worked out or a single home made obvious.
            'said'        => $said,
            'with_you'    => $with,
            'until'       => $stay['until'] ?? '',
            'until_label' => $this->say_date( $stay['until'] ?? '', $today ),
            'next_id'     => $next['id'] ?? 0,
            'next_name'   => $next['name'] ?? '',
        ];
    }

    /**
     * The fortnight ahead as one list: what is due, who is moving, and whose
     * birthday it is, across every home the viewer belongs to.
     *
     * @return array[] each entry naming its `kind`, dated and said out loud.
     */
    private function get_agenda( int $user_id, int $viewer, string $today, int $days ): array {
        $horizon = ( new \DateTimeImmutable( $today, new \DateTimeZone( 'UTC' ) ) )
            ->modify( '+' . $days . ' days' )->format( 'Y-m-d' );

        $entries = array_merge(
            $this->agenda_due( $user_id, $viewer, $today, $horizon ),
            $this->agenda_moves( $user_id, $today, $days ),
            $this->agenda_birthdays( $user_id, $days )
        );

        usort( $entries, static function( array $a, array $b ): int {
            return strcmp( $a['date'], $b['date'] ) ?: strcmp( $a['kind'], $b['kind'] );
        } );

        foreach ( $entries as $index => $entry ) {
            $entries[ $index ]['when'] = $this->say_date( $entry['date'], $today );
        }
        return $entries;
    }

    /**
     * Appointments and dated tasks falling inside the window.
     *
     * Someone organising a home sees everything written down in it; anyone else
     * sees what is theirs and what is the house's, which is the rule the home
     * page itself reads by.
     */
    private function agenda_due( int $user_id, int $viewer, string $from, string $until ): array {
        $entries = [];
        foreach ( $this->get_homes_for_user( $user_id ) as $home ) {
            $sees_everything = Access::can_organise( $user_id, $home['id'] );
            foreach ( $this->get_tasks( $home['id'] ) as $task ) {
                if ( $task['is_done'] || '' === $task['due_date'] ) {
                    continue;
                }
                if ( $task['due_date'] < $from || $task['due_date'] > $until ) {
                    continue;
                }
                if ( ! $sees_everything && $task['person_id'] && $task['person_id'] !== $viewer ) {
                    continue;
                }
                $entries[] = [
                    'date'      => $task['due_date'],
                    'kind'      => 'appointment' === $task['task_type'] ? 'appointment' : 'task',
                    'title'     => $task['title'],
                    'who'       => $task['person'],
                    'home_id'   => $home['id'],
                    'home_name' => $home['name'],
                ];
            }
        }
        return $entries;
    }

    /**
     * The moves the rotations imply, from the viewer's side of them: the ones
     * that arrive at or leave one of their homes, and no others. People moving
     * the same way on the same day are one move, because that is one trip.
     */
    private function agenda_moves( int $user_id, string $today, int $days ): array {
        $mine = Access::home_ids_for_user( $user_id );
        $moves = [];
        $seen = [];
        foreach ( $mine as $home_id ) {
            foreach ( Access::person_ids_in_home( $home_id ) as $person_id ) {
                if ( isset( $seen[ $person_id ] ) ) {
                    continue;
                }
                $seen[ $person_id ] = true;
                $person = $this->get_person( $person_id );
                if ( ! $person ) {
                    continue;
                }
                $previous = null;
                foreach ( Whereabouts::days_for_person( $person_id, $today, $days + 1 ) as $day ) {
                    $moved = null !== $previous && $previous['home_id'] && $day['home_id']
                        && $day['home_id'] !== $previous['home_id'];
                    $concerns_me = $moved
                        && ( in_array( $day['home_id'], $mine, true ) || in_array( $previous['home_id'], $mine, true ) );
                    if ( $concerns_me ) {
                        $key = $day['date'] . ':' . $previous['home_id'] . ':' . $day['home_id'];
                        if ( ! isset( $moves[ $key ] ) ) {
                            $from = $this->get_home( $previous['home_id'] );
                            $to = $this->get_home( $day['home_id'] );
                            $moves[ $key ] = [
                                'date'      => $day['date'],
                                'kind'      => 'move',
                                'title'     => '',
                                'who'       => '',
                                'people'    => [],
                                'from_name' => $from['name'] ?? '',
                                'home_id'   => $to['id'] ?? 0,
                                'home_name' => $to['name'] ?? '',
                            ];
                        }
                        $moves[ $key ]['people'][] = $person['name'];
                    }
                    $previous = $day;
                }
            }
        }
        return array_values( $moves );
    }

    /** Birthdays inside the window, each person once however many homes they are in. */
    private function agenda_birthdays( int $user_id, int $days ): array {
        $entries = [];
        $seen = [];
        foreach ( Access::home_ids_for_user( $user_id ) as $home_id ) {
            foreach ( $this->get_upcoming_birthdays( $home_id ) as $birthday ) {
                if ( isset( $seen[ $birthday['id'] ] ) || $birthday['days_until'] > $days ) {
                    continue;
                }
                $seen[ $birthday['id'] ] = true;
                $entries[] = [
                    'date'      => $birthday['date'],
                    'kind'      => 'birthday',
                    'title'     => $birthday['name'],
                    'who'       => '',
                    'turning'   => $birthday['turning'],
                    'home_id'   => 0,
                    'home_name' => '',
                ];
            }
        }
        return $entries;
    }

    /**
     * A date as the app says it out loud, in a sentence: today, tomorrow, or
     * the weekday and the date.
     */
    public function say_date( string $date, string $today = '' ): string {
        $today = $today ?: current_time( 'Y-m-d' );
        if ( '' === $this->normalize_date( $date ) ) {
            return '';
        }
        $utc = new \DateTimeZone( 'UTC' );
        if ( $date === $today ) {
            return __( 'today', 'households' );
        }
        if ( $date === ( new \DateTimeImmutable( $today, $utc ) )->modify( '+1 day' )->format( 'Y-m-d' ) ) {
            return __( 'tomorrow', 'households' );
        }
        return wp_date( 'D j M', ( new \DateTimeImmutable( $date, $utc ) )->getTimestamp(), $utc );
    }

    /* ---------------------------------------------------------------- Whereabouts */

    /**
     * Where a person is today, as far as this app can honestly say: what they
     * said for today, else what their rotation works out, else the last thing
     * they said, else the single home they belong to. Someone who belongs to
     * several, rotates between none and has never said where they are is not
     * tracked, and saying where they are would be a guess dressed up as an
     * answer — so the app asks them instead.
     *
     * @return array{home_id:int,name:string,known:bool}
     */
    public function location_today( int $person_id ): array {
        $home_id = Whereabouts::home_on( $person_id, current_time( 'Y-m-d' ) )['home_id'];
        $home = $home_id ? $this->get_home( $home_id ) : [];
        return [
            'home_id' => $home_id,
            'name'    => $home['name'] ?? '',
            'known'   => (bool) $home_id,
        ];
    }

    /** @return string 'here', 'away' or 'unknown'. */
    private function presence( int $person_id, int $home_id ): string {
        $location = $this->location_today( $person_id );
        if ( ! $location['known'] ) {
            return 'unknown';
        }
        return $location['home_id'] === $home_id ? 'here' : 'away';
    }

    /** Who is under this roof today. */
    public function who_is_here( int $home_id ): array {
        return $this->people_by_presence( $home_id, 'here' );
    }

    /**
     * Who belongs here but could be anywhere: no rotation, and more than one
     * home to be at. Reported rather than hidden, so an empty roof is
     * distinguishable from one the app cannot account for.
     */
    public function whereabouts_unknown( int $home_id ): array {
        return $this->people_by_presence( $home_id, 'unknown' );
    }

    private function people_by_presence( int $home_id, string $presence ): array {
        $people = [];
        foreach ( $this->get_people( $home_id ) as $person ) {
            if ( $this->presence( $person['id'], $home_id ) !== $presence ) {
                continue;
            }
            $people[] = [
                'id'       => $person['id'],
                'name'     => $person['name'],
                'is_child' => $person['is_child'],
            ];
        }
        return $people;
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

        // Each person is walked from the day before the window, so a move onto
        // its first day is a handover like any other. Without it, paging
        // forward would hide exactly the arrival that made you page.
        $lead = ( new \DateTimeImmutable( $start, new \DateTimeZone( 'UTC' ) ) )->modify( '-1 day' )->format( 'Y-m-d' );

        $people = [];
        $walks = [];
        foreach ( $this->get_people( $home_id ) as $person ) {
            $rotation = Whereabouts::get_rotation( $person['id'] );
            $days_out = [];
            foreach ( Whereabouts::days_for_person( $person['id'], $lead, $days + 1 ) as $day ) {
                $home = $this->get_home( $day['home_id'] );
                $days_out[] = [
                    'date'        => $day['date'],
                    'home_id'     => $day['home_id'],
                    'home_name'   => $home['name'] ?? '',
                    'is_here'     => $day['home_id'] === $home_id,
                    'is_override' => $day['is_override'],
                    'is_carried'  => $day['is_carried'],
                ];
            }
            $walks[] = [ 'name' => $person['name'], 'days' => $days_out ];
            $days_out = array_slice( $days_out, 1 );
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
            'here'      => $this->who_is_here( $home_id ),
            'unknown'   => $this->whereabouts_unknown( $home_id ),
            'dates'     => $dates,
            'people'    => $people,
            'handovers' => $this->collect_handovers( $walks, $home_id ),
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
                // A day nobody can account for is not somewhere to travel from
                // or to, so it starts no handover.
                $moved = null !== $previous && $previous['home_id'] && $day['home_id']
                    && $day['home_id'] !== $previous['home_id'];
                if ( $moved ) {
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
