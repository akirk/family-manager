<?php

namespace Households;

/**
 * Which home someone is at on any given day.
 *
 * Anyone who belongs to more than one household can rotate between them: a
 * child between two parents, but equally a family splitting the week between
 * town and the grandparents, or a member who spends every third week at the
 * holiday house. A rotation names its homes in order and repeats a cycle of
 * days from a start date; the homes are a list, not a pair, so a rotation can
 * take in as many places as the family actually uses.
 *
 * The rotation is stored on the member rather than on any one household, so
 * every home reads the same answer.
 *
 * A single day is moved with an override, which wins over the cycle and leaves
 * the pattern untouched — a swapped weekend should not shift every week that
 * follows it.
 */
class Whereabouts {
    public const META_ROTATION  = '_households_rotation';
    public const META_OVERRIDES = '_households_rotation_overrides';

    public const PATTERN_WEEK               = 'week';
    public const PATTERN_2_2_3              = '2-2-3';
    public const PATTERN_ALTERNATE_WEEKENDS = 'alternate_weekends';
    public const PATTERN_CUSTOM             = 'custom';

    public const CYCLE_DAYS = 14;

    public const DEFAULT_CHANGEOVER_TIME = '17:00';

    /**
     * The rotation patterns, as a cycle of days.
     *
     * Each `cycle` entry is a position in the rotation's list of homes, so 0 is
     * the first home and 1 the second. The cycle repeats from the start date,
     * which is why every pattern says what its first day means. The ready-made
     * patterns alternate between the first two homes; a custom cycle can use
     * every home in the list.
     *
     * @return array<string,array{label:string,cycle:int[],start_hint:string,homes:int}>
     */
    public static function patterns(): array {
        return [
            self::PATTERN_WEEK => [
                'label'      => __( 'Week on, week off', 'households' ),
                'cycle'      => [ 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1 ],
                'start_hint' => __( 'Pick the day a week at the first home begins.', 'households' ),
                'homes'      => 2,
            ],
            self::PATTERN_2_2_3 => [
                'label'      => __( '2-2-3', 'households' ),
                'cycle'      => [ 0, 0, 1, 1, 0, 0, 0, 1, 1, 0, 0, 1, 1, 1 ],
                'start_hint' => __( 'Pick a Monday at the first home; the long weekend alternates.', 'households' ),
                'homes'      => 2,
            ],
            self::PATTERN_ALTERNATE_WEEKENDS => [
                'label'      => __( 'Every other weekend', 'households' ),
                'cycle'      => [ 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ],
                'start_hint' => __( 'Pick a Friday spent at the second home.', 'households' ),
                'homes'      => 2,
            ],
            self::PATTERN_CUSTOM => [
                'label'      => __( 'Custom two weeks', 'households' ),
                'cycle'      => [ 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1 ],
                'start_hint' => __( 'Set each of the fourteen days yourself, across every home in the list.', 'households' ),
                'homes'      => 0,
            ],
        ];
    }

    /* ---------------------------------------------------------------- Rotations */

    /**
     * A member's rotation, or an empty array if they have none.
     *
     * Homes the member has since left are dropped, so a stale rotation never
     * sends anyone to a house they no longer belong to. What is left has to
     * still be a rotation: two homes at the least.
     *
     * @return array{pattern:string,start_date:string,homes:int[],changeover_time:string,cycle:int[]}|array{}
     */
    public static function get_rotation( int $user_id ): array {
        $stored = get_user_meta( $user_id, self::META_ROTATION, true );
        if ( ! is_array( $stored ) || empty( $stored['homes'] ) ) {
            return [];
        }

        $homes = self::filter_homes( $user_id, (array) $stored['homes'] );
        if ( count( $homes ) < 2 ) {
            return [];
        }

        $pattern = isset( $stored['pattern'] ) && isset( self::patterns()[ $stored['pattern'] ] ) ? $stored['pattern'] : self::PATTERN_WEEK;
        $cycle = self::PATTERN_CUSTOM === $pattern && ! empty( $stored['cycle'] )
            ? self::normalize_cycle( (array) $stored['cycle'], count( $homes ) )
            : self::patterns()[ $pattern ]['cycle'];

        return [
            'pattern'         => $pattern,
            'start_date'      => self::normalize_date( (string) ( $stored['start_date'] ?? '' ) ) ?: self::today(),
            'homes'           => $homes,
            'changeover_time' => self::normalize_time( (string) ( $stored['changeover_time'] ?? '' ) ),
            'cycle'           => $cycle,
        ];
    }

    /**
     * Store a rotation for a member.
     *
     * @param array{pattern?:string,start_date?:string,homes?:int[],changeover_time?:string,cycle?:int[]} $input
     * @return bool False when the input does not describe a usable rotation.
     */
    public static function save_rotation( int $user_id, array $input ): bool {
        $homes = self::filter_homes( $user_id, (array) ( $input['homes'] ?? [] ) );
        if ( count( $homes ) < 2 ) {
            return false;
        }

        $pattern = isset( $input['pattern'] ) && isset( self::patterns()[ $input['pattern'] ] ) ? $input['pattern'] : self::PATTERN_WEEK;
        $cycle = self::PATTERN_CUSTOM === $pattern
            ? self::normalize_cycle( (array) ( $input['cycle'] ?? [] ), count( $homes ) )
            : self::patterns()[ $pattern ]['cycle'];

        update_user_meta( $user_id, self::META_ROTATION, [
            'pattern'         => $pattern,
            'start_date'      => self::normalize_date( (string) ( $input['start_date'] ?? '' ) ) ?: self::today(),
            'homes'           => $homes,
            'changeover_time' => self::normalize_time( (string) ( $input['changeover_time'] ?? '' ) ),
            'cycle'           => $cycle,
        ] );

        return true;
    }

    public static function clear_rotation( int $user_id ): void {
        delete_user_meta( $user_id, self::META_ROTATION );
        delete_user_meta( $user_id, self::META_OVERRIDES );
    }

    /** Whether a member could have a rotation at all: they need somewhere to rotate to. */
    public static function can_rotate( int $user_id ): bool {
        return count( Access::household_ids_for_user( $user_id ) ) > 1;
    }

    /* ---------------------------------------------------------------- Overrides */

    /** @return array<string,int> date => household ID, oldest first. */
    public static function get_overrides( int $user_id ): array {
        $stored = get_user_meta( $user_id, self::META_OVERRIDES, true );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $overrides = [];
        foreach ( $stored as $date => $household_id ) {
            $date = self::normalize_date( (string) $date );
            $household_id = (int) $household_id;
            if ( '' !== $date && Access::is_member( $user_id, $household_id ) ) {
                $overrides[ $date ] = $household_id;
            }
        }
        ksort( $overrides );
        return $overrides;
    }

    /**
     * Move a single day, or hand it back to the pattern with a household ID of 0.
     */
    public static function set_override( int $user_id, string $date, int $household_id ): bool {
        $date = self::normalize_date( $date );
        if ( '' === $date ) {
            return false;
        }
        if ( $household_id && ! Access::is_member( $user_id, $household_id ) ) {
            return false;
        }

        $overrides = self::get_overrides( $user_id );
        if ( $household_id ) {
            $overrides[ $date ] = $household_id;
        } else {
            unset( $overrides[ $date ] );
        }

        update_user_meta( $user_id, self::META_OVERRIDES, $overrides );
        return true;
    }

    /** Forget overrides that are in the past, so the list stays about what is ahead. */
    public static function prune_overrides( int $user_id ): void {
        $today = self::today();
        $overrides = array_filter( self::get_overrides( $user_id ), static function( string $date ) use ( $today ): bool {
            return $date >= $today;
        }, ARRAY_FILTER_USE_KEY );
        update_user_meta( $user_id, self::META_OVERRIDES, $overrides );
    }

    /* ---------------------------------------------------------------- Lookups */

    /**
     * Which home a member is at on a date.
     *
     * @return array{household_id:int,is_override:bool}
     */
    public static function home_on( int $user_id, string $date ): array {
        $date = self::normalize_date( $date );
        $rotation = self::get_rotation( $user_id );
        if ( '' === $date || ! $rotation ) {
            return [ 'household_id' => 0, 'is_override' => false ];
        }

        $overrides = self::get_overrides( $user_id );
        if ( isset( $overrides[ $date ] ) ) {
            return [ 'household_id' => $overrides[ $date ], 'is_override' => true ];
        }

        return [ 'household_id' => self::home_from_cycle( $rotation, $date ), 'is_override' => false ];
    }

    /**
     * A member's whereabouts over a run of days.
     *
     * @return array[] one entry per day: date, household_id, is_override.
     */
    public static function days_for_member( int $user_id, string $start, int $days ): array {
        $rotation = self::get_rotation( $user_id );
        if ( ! $rotation ) {
            return [];
        }

        $overrides = self::get_overrides( $user_id );
        $cursor = \DateTimeImmutable::createFromFormat( '!Y-m-d', self::normalize_date( $start ) ?: self::today() );
        $out = [];
        for ( $i = 0; $i < max( 1, $days ); $i++ ) {
            $date = $cursor->format( 'Y-m-d' );
            $out[] = [
                'date'         => $date,
                'household_id' => $overrides[ $date ] ?? self::home_from_cycle( $rotation, $date ),
                'is_override'  => isset( $overrides[ $date ] ),
            ];
            $cursor = $cursor->modify( '+1 day' );
        }
        return $out;
    }

    /**
     * The day a member's current stay ends: the first date after `$from` on
     * which they are somewhere else.
     *
     * @return array{until:string,next_household_id:int}|array{} Empty when the
     *         stay runs past the horizon, or the member has no rotation.
     */
    public static function stay_ends( int $user_id, string $from, int $horizon = 60 ): array {
        $days = self::days_for_member( $user_id, $from, $horizon );
        if ( ! $days ) {
            return [];
        }
        $current = $days[0]['household_id'];
        foreach ( $days as $day ) {
            if ( $day['household_id'] !== $current ) {
                return [ 'until' => $day['date'], 'next_household_id' => $day['household_id'] ];
            }
        }
        return [];
    }

    /* ---------------------------------------------------------------- Helpers */

    private static function home_from_cycle( array $rotation, string $date ): int {
        $cycle = $rotation['cycle'];
        $length = count( $cycle );
        if ( ! $length ) {
            return 0;
        }

        $start = \DateTimeImmutable::createFromFormat( '!Y-m-d', $rotation['start_date'] );
        $day = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
        if ( ! $start || ! $day ) {
            return 0;
        }

        $offset = (int) $start->diff( $day )->days * ( $day < $start ? -1 : 1 );
        $index = ( ( $offset % $length ) + $length ) % $length;
        $slot = (int) ( $cycle[ $index ] ?? 0 );

        return $rotation['homes'][ $slot ] ?? $rotation['homes'][0];
    }

    /** Homes in the order given, keeping only those the member still belongs to. */
    private static function filter_homes( int $user_id, array $homes ): array {
        return array_values( array_unique( array_filter(
            array_map( 'intval', $homes ),
            static function( int $household_id ) use ( $user_id ): bool {
                return Access::is_member( $user_id, $household_id );
            }
        ) ) );
    }

    /** A cycle is fourteen slots, each naming one of the rotation's homes. */
    private static function normalize_cycle( array $cycle, int $home_count ): array {
        $normalized = [];
        for ( $i = 0; $i < self::CYCLE_DAYS; $i++ ) {
            $slot = (int) ( $cycle[ $i ] ?? 0 );
            $normalized[] = $slot >= 0 && $slot < $home_count ? $slot : 0;
        }
        return $normalized;
    }

    private static function normalize_date( string $date ): string {
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
    }

    private static function normalize_time( string $time ): string {
        return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : self::DEFAULT_CHANGEOVER_TIME;
    }

    private static function today(): string {
        return current_time( 'Y-m-d' );
    }
}
