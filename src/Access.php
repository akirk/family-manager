<?php

namespace Households;

/**
 * Who may see or change what.
 *
 * A home is a term. A person is a post, tagged with the term of every home they
 * belong to, and authored by their WordPress user — or by nobody, which is how
 * a toddler or a pet gets a record without an account it would never use.
 *
 * Only two things are actually decided here. Whether someone administers a
 * home, which is per home and lives in term meta; and whether someone is a
 * child, which is a property of the person and true wherever they go. Both are
 * reachable through the ordinary capability API as `manage_household` and
 * `organise_household`, each taking a home's term ID.
 */
class Access {
    public const TAXONOMY = 'household';
    public const PERSON   = 'household_person';

    /** Term meta: WP user IDs who administer this home. */
    public const TERM_ADMINS = '_households_admins';

    /** Post meta on a person. */
    public const META_IS_CHILD = '_households_is_child';

    /** @var array<int,int> user ID => person post ID, for this request. */
    private static $person_by_user = [];

    public static function init(): void {
        add_filter( 'map_meta_cap', [ self::class, 'map_meta_cap' ], 10, 4 );
    }

    /* ---------------------------------------------------------------- People */

    /**
     * The person record belonging to a WordPress user, or 0 if they have none.
     *
     * Someone can hold an account without a record — an administrator who set
     * the site up, say — and they are not a member of anything until one exists.
     */
    public static function person_for_user( int $user_id ): int {
        if ( ! $user_id ) {
            return 0;
        }
        if ( isset( self::$person_by_user[ $user_id ] ) ) {
            return self::$person_by_user[ $user_id ];
        }
        $ids = get_posts( [
            'author'           => $user_id,
            'fields'           => 'ids',
            'numberposts'      => 1,
            'post_status'      => 'private',
            'post_type'        => self::PERSON,
            'suppress_filters' => false,
        ] );
        self::$person_by_user[ $user_id ] = $ids ? (int) $ids[0] : 0;
        return self::$person_by_user[ $user_id ];
    }

    /** The WordPress user behind a person, or 0 when nobody logs in as them. */
    public static function user_for_person( int $person_id ): int {
        $person = get_post( $person_id );
        return $person && self::PERSON === $person->post_type ? (int) $person->post_author : 0;
    }

    public static function flush_person_cache(): void {
        self::$person_by_user = [];
    }

    public static function is_person( int $person_id ): bool {
        $person = get_post( $person_id );
        return (bool) $person && self::PERSON === $person->post_type;
    }

    /** A child is a child at every home they go to, so this is not per home. */
    public static function is_child( int $person_id ): bool {
        return (bool) get_post_meta( $person_id, self::META_IS_CHILD, true );
    }

    /* ---------------------------------------------------------------- Membership */

    /** @return int[] term IDs of the homes this person belongs to. */
    public static function home_ids_for_person( int $person_id ): array {
        if ( ! $person_id ) {
            return [];
        }
        $terms = wp_get_object_terms( $person_id, self::TAXONOMY, [ 'fields' => 'ids' ] );
        return is_wp_error( $terms ) ? [] : array_map( 'intval', $terms );
    }

    /** @return int[] term IDs of the homes this user belongs to. */
    public static function home_ids_for_user( int $user_id ): array {
        return self::home_ids_for_person( self::person_for_user( $user_id ) );
    }

    /** @return int[] person post IDs tagged with this home, oldest first. */
    public static function person_ids_in_home( int $home_id ): array {
        if ( ! $home_id ) {
            return [];
        }
        return array_map( 'intval', get_posts( [
            'fields'           => 'ids',
            'numberposts'      => -1,
            'order'            => 'ASC',
            'orderby'          => 'ID',
            'post_status'      => 'private',
            'post_type'        => self::PERSON,
            'suppress_filters' => false,
            'tax_query'        => [
                [
                    'field'    => 'term_id',
                    'taxonomy' => self::TAXONOMY,
                    'terms'    => $home_id,
                ],
            ],
        ] ) );
    }

    public static function is_member( int $person_id, int $home_id ): bool {
        return $person_id && $home_id && in_array( $home_id, self::home_ids_for_person( $person_id ), true );
    }

    public static function join( int $person_id, int $home_id ): void {
        wp_set_object_terms( $person_id, [ $home_id ], self::TAXONOMY, true );
    }

    /**
     * Take a person out of a home. The record itself survives — someone leaving
     * a household should not erase who they are, or what was written about them.
     */
    public static function leave( int $person_id, int $home_id ): void {
        wp_remove_object_terms( $person_id, [ $home_id ], self::TAXONOMY );
        self::set_admin( $home_id, self::user_for_person( $person_id ), false );
    }

    /* ---------------------------------------------------------------- Administrators */

    /** @return int[] WP user IDs who administer this home. */
    public static function admins_of( int $home_id ): array {
        $stored = get_term_meta( $home_id, self::TERM_ADMINS, true );
        return is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'intval', $stored ) ) ) ) : [];
    }

    public static function set_admin( int $home_id, int $user_id, bool $is_admin ): void {
        if ( ! $user_id ) {
            return;
        }
        $admins = self::admins_of( $home_id );
        $admins = array_values( array_diff( $admins, [ $user_id ] ) );
        if ( $is_admin ) {
            $admins[] = $user_id;
        }
        update_term_meta( $home_id, self::TERM_ADMINS, $admins );
    }

    /* ---------------------------------------------------------------- Decisions */

    /** Administers this home: may change its settings, members and rotations. */
    public static function can_manage( int $user_id, int $home_id ): bool {
        return $user_id && in_array( $user_id, self::admins_of( $home_id ), true );
    }

    /** Belongs to this home and is not a child: may add and assign things. */
    public static function can_organise( int $user_id, int $home_id ): bool {
        $person = self::person_for_user( $user_id );
        return self::is_member( $person, $home_id ) && ! self::is_child( $person );
    }

    /**
     * May this user look at that person's own view? True for themselves, and
     * for anyone administering a home the person belongs to.
     */
    public static function can_view_person( int $user_id, int $person_id ): bool {
        if ( ! $person_id ) {
            return false;
        }
        if ( self::person_for_user( $user_id ) === $person_id ) {
            return true;
        }
        foreach ( self::home_ids_for_person( $person_id ) as $home_id ) {
            if ( self::can_manage( $user_id, $home_id ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * `current_user_can( 'manage_household', $home_id )` and its organising
     * counterpart, so the rest of the plugin asks WordPress rather than us.
     */
    public static function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
        if ( 'manage_household' !== $cap && 'organise_household' !== $cap ) {
            return $caps;
        }
        $home_id = isset( $args[0] ) ? (int) $args[0] : 0;
        if ( ! $home_id ) {
            return [ 'do_not_allow' ];
        }
        $allowed = 'manage_household' === $cap
            ? self::can_manage( $user_id, $home_id )
            : self::can_organise( $user_id, $home_id );

        return $allowed ? [ 'read' ] : [ 'do_not_allow' ];
    }
}
