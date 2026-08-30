<?php

namespace Households;

/**
 * Answers "who may see or change what".
 *
 * Households store their membership as post meta: a map of user ID => role.
 * Roles are household-scoped, so the same user can be an admin of one
 * household and a plain member of another.
 */
class Access {
    public const META_MEMBERS = '_households_members';

    public const ROLE_ADMIN       = 'admin';
    public const ROLE_PARENT      = 'parent';
    public const ROLE_CHILD       = 'child';
    public const ROLE_GRANDPARENT = 'grandparent';
    public const ROLE_CAREGIVER   = 'caregiver';

    public static function roles(): array {
        return [
            self::ROLE_ADMIN       => __( 'Administrator', 'households' ),
            self::ROLE_PARENT      => __( 'Parent', 'households' ),
            self::ROLE_CHILD       => __( 'Child', 'households' ),
            self::ROLE_GRANDPARENT => __( 'Grandparent', 'households' ),
            self::ROLE_CAREGIVER   => __( 'Caregiver', 'households' ),
        ];
    }

    /** Roles that may see other members' views and manage the household. */
    public static function managing_roles(): array {
        return [ self::ROLE_ADMIN ];
    }

    /** Roles that may add tasks/rewards and assign them to others. */
    public static function organising_roles(): array {
        return [ self::ROLE_ADMIN, self::ROLE_PARENT, self::ROLE_GRANDPARENT, self::ROLE_CAREGIVER ];
    }

    /** @return array<int,string> user ID => role */
    public static function members_of( int $household_id ): array {
        $members = get_post_meta( $household_id, self::META_MEMBERS, true );
        if ( ! is_array( $members ) ) {
            return [];
        }
        $clean = [];
        foreach ( $members as $user_id => $role ) {
            $user_id = (int) $user_id;
            if ( $user_id && isset( self::roles()[ $role ] ) ) {
                $clean[ $user_id ] = $role;
            }
        }
        return $clean;
    }

    public static function set_member_role( int $household_id, int $user_id, string $role ): void {
        $members = self::members_of( $household_id );
        $members[ $user_id ] = isset( self::roles()[ $role ] ) ? $role : self::ROLE_CHILD;
        update_post_meta( $household_id, self::META_MEMBERS, $members );
        self::flush_user_cache( $user_id );
    }

    public static function remove_member( int $household_id, int $user_id ): void {
        $members = self::members_of( $household_id );
        unset( $members[ $user_id ] );
        update_post_meta( $household_id, self::META_MEMBERS, $members );
        self::flush_user_cache( $user_id );
    }

    public static function role_in_household( int $user_id, int $household_id ): string {
        return self::members_of( $household_id )[ $user_id ] ?? '';
    }

    /** @return int[] household IDs the user belongs to, oldest first. */
    public static function household_ids_for_user( int $user_id ): array {
        $ids = get_posts( [
            'fields'           => 'ids',
            'numberposts'      => -1,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'post_status'      => 'private',
            'post_type'        => 'household',
            'suppress_filters' => false,
            'meta_query'       => [
                [
                    'key'     => self::META_MEMBERS,
                    'value'   => 'i:' . $user_id . ';',
                    'compare' => 'LIKE',
                ],
            ],
        ] );
        // The LIKE match on serialized data is only a pre-filter; confirm membership.
        return array_values( array_filter( array_map( 'intval', $ids ), static function( int $household_id ) use ( $user_id ): bool {
            return '' !== self::role_in_household( $user_id, $household_id );
        } ) );
    }

    public static function is_member( int $user_id, int $household_id ): bool {
        return '' !== self::role_in_household( $user_id, $household_id );
    }

    public static function can_manage( int $user_id, int $household_id ): bool {
        return in_array( self::role_in_household( $user_id, $household_id ), self::managing_roles(), true );
    }

    public static function can_organise( int $user_id, int $household_id ): bool {
        return in_array( self::role_in_household( $user_id, $household_id ), self::organising_roles(), true );
    }

    /**
     * May $viewer look at $subject's personal view? True for oneself and for
     * managers of any household the subject belongs to.
     */
    public static function can_view_user( int $viewer_id, int $subject_id ): bool {
        if ( $viewer_id === $subject_id ) {
            return true;
        }
        foreach ( self::household_ids_for_user( $subject_id ) as $household_id ) {
            if ( self::can_manage( $viewer_id, $household_id ) ) {
                return true;
            }
        }
        return false;
    }

    private static function flush_user_cache( int $user_id ): void {
        clean_user_cache( $user_id );
    }
}
