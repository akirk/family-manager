<?php

namespace Households;

/**
 * Which home someone is at on any given day.
 *
 * Anyone who belongs to more than one home can rotate between them: a child
 * between two parents, but equally a family splitting the week between town and
 * the grandparents, or someone who spends every third week at the holiday
 * house. A rotation names its homes in order and repeats a cycle of days from a
 * start date; the homes are a list, not a pair, so it can take in as many places
 * as the family actually uses.
 *
 * The rotation lives on the person, not on any one home, so every home reads
 * the same answer.
 *
 * A single day is said outright with an override, which wins over the cycle and
 * leaves the pattern untouched — a swapped weekend should not shift every week
 * after it. The days ahead can be said the same way, in a run, which is how a
 * stay is planned rather than a day swapped: still days, still leaving the
 * pattern alone, and the pattern picks up again when the run ends.
 *
 * A day can be said with no pattern behind it at all, which is how someone who
 * belongs to several homes and rotates between none answers the question
 * without inventing an arrangement they do not have. With nothing else to
 * answer for the days that follow, that statement stands until another one is
 * made: somebody who moved on Tuesday is still there on Wednesday. So the
 * fortnight after a move reads as the move — the same home, day after day —
 * rather than as a single marked day and then a blank.
 */
class Whereabouts {
    public const META_ROTATION  = '_households_rotation';
    public const META_OVERRIDES = '_households_rotation_overrides';

    public const PATTERN_WEEK               = 'week';
    public const PATTERN_2_2_3              = '2-2-3';
    public const PATTERN_ALTERNATE_WEEKENDS = 'alternate_weekends';
    public const PATTERN_CUSTOM             = 'custom';

    public const CYCLE_DAYS = 14;

    /**
     * The longest run of days one tap may say at once: the same horizon the
     * board can show, because a run is said by tapping a day on it.
     */
    public const MAX_RUN_DAYS = 56;

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
                'start_hint' => __( 'Pick the day a week at the first household begins.', 'households' ),
                'homes'      => 2,
            ],
            self::PATTERN_2_2_3 => [
                'label'      => __( '2-2-3', 'households' ),
                'cycle'      => [ 0, 0, 1, 1, 0, 0, 0, 1, 1, 0, 0, 1, 1, 1 ],
                'start_hint' => __( 'Pick a Monday at the first household; the long weekend alternates.', 'households' ),
                'homes'      => 2,
            ],
            self::PATTERN_ALTERNATE_WEEKENDS => [
                'label'      => __( 'Every other weekend', 'households' ),
                'cycle'      => [ 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ],
                'start_hint' => __( 'Pick a Friday spent at the second household.', 'households' ),
                'homes'      => 2,
            ],
            self::PATTERN_CUSTOM => [
                'label'      => __( 'Custom two weeks', 'households' ),
                'cycle'      => [ 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1 ],
                'start_hint' => __( 'Set each of the fourteen days yourself, across every household in the list.', 'households' ),
                'homes'      => 0,
            ],
        ];
    }

    /* ---------------------------------------------------------------- Rotations */

    /**
     * A person's rotation, or an empty array if they have none.
     *
     * Homes they have since left are dropped, so a stale rotation never sends
     * anyone to a house they no longer belong to. What is left has to still be
     * a rotation: two homes at the least.
     *
     * @return array{pattern:string,start_date:string,homes:int[],changeover_time:string,cycle:int[]}|array{}
     */
    public static function get_rotation( int $person_id ): array {
        $stored = get_post_meta( $person_id, self::META_ROTATION, true );
        if ( ! is_array( $stored ) || empty( $stored['homes'] ) ) {
            return [];
        }

        $homes = self::filter_homes( $person_id, (array) $stored['homes'] );
        if ( count( $homes ) < 2 ) {
            return [];
        }

        $pattern = isset( $stored['pattern'], self::patterns()[ $stored['pattern'] ] ) ? $stored['pattern'] : self::PATTERN_WEEK;
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
     * Store a rotation for a person.
     *
     * @param array{pattern?:string,start_date?:string,homes?:int[],changeover_time?:string,cycle?:int[]} $input
     * @return bool False when the input does not describe a usable rotation.
     */
    public static function save_rotation( int $person_id, array $input ): bool {
        $homes = self::filter_homes( $person_id, (array) ( $input['homes'] ?? [] ) );
        if ( count( $homes ) < 2 ) {
            return false;
        }

        $pattern = isset( $input['pattern'], self::patterns()[ $input['pattern'] ] ) ? $input['pattern'] : self::PATTERN_WEEK;
        $cycle = self::PATTERN_CUSTOM === $pattern
            ? self::normalize_cycle( (array) ( $input['cycle'] ?? [] ), count( $homes ) )
            : self::patterns()[ $pattern ]['cycle'];

        update_post_meta( $person_id, self::META_ROTATION, [
            'pattern'         => $pattern,
            'start_date'      => self::normalize_date( (string) ( $input['start_date'] ?? '' ) ) ?: self::today(),
            'homes'           => $homes,
            'changeover_time' => self::normalize_time( (string) ( $input['changeover_time'] ?? '' ) ),
            'cycle'           => $cycle,
        ] );

        return true;
    }

    public static function clear_rotation( int $person_id ): void {
        delete_post_meta( $person_id, self::META_ROTATION );
        delete_post_meta( $person_id, self::META_OVERRIDES );
    }

    /** Whether a person could rotate at all: they need somewhere to rotate to. */
    public static function can_rotate( int $person_id ): bool {
        return count( Access::home_ids_for_person( $person_id ) ) > 1;
    }

    /* ---------------------------------------------------------------- Overrides */

    /** @return array<string,int> date => home term ID, oldest first. */
    public static function get_overrides( int $person_id ): array {
        $stored = get_post_meta( $person_id, self::META_OVERRIDES, true );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $overrides = [];
        foreach ( $stored as $date => $home_id ) {
            $date = self::normalize_date( (string) $date );
            $home_id = (int) $home_id;
            if ( '' !== $date && Access::is_member( $person_id, $home_id ) ) {
                $overrides[ $date ] = $home_id;
            }
        }
        ksort( $overrides );
        return $overrides;
    }

    /** Say where someone is on a day, or take it back with a home ID of 0. */
    public static function set_override( int $person_id, string $date, int $home_id ): bool {
        return self::set_override_run( $person_id, $date, 1, $home_id );
    }

    /**
     * Say where someone is from a day onwards, for a run of days.
     *
     * A day said on its own is a swap; a run of them is a plan — where you
     * will be for the days ahead, so the rest of the family can see it and
     * work around it. Both are the same thing written down: days, said
     * outright. The pattern is untouched either way, and picks up again the
     * moment the run ends, which is why a run says how long it is rather than
     * stretching off into a future nobody has thought about yet.
     *
     * A home ID of 0 hands the whole run back to the pattern.
     */
    public static function set_override_run( int $person_id, string $date, int $days, int $home_id ): bool {
        $date = self::normalize_date( $date );
        if ( '' === $date ) {
            return false;
        }
        if ( $home_id && ! Access::is_member( $person_id, $home_id ) ) {
            return false;
        }
        $cursor = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
        if ( ! $cursor ) {
            return false;
        }

        $overrides = self::get_overrides( $person_id );
        $rotates = (bool) self::get_rotation( $person_id );
        $days = max( 1, min( self::MAX_RUN_DAYS, $days ) );
        for ( $i = 0; $i < $days; $i++ ) {
            $day = $cursor->format( 'Y-m-d' );
            // With a pattern behind them every day of the run has to be said,
            // because the pattern answers for any day that is not. With no
            // pattern, the first day is the whole statement — it stands until
            // something else is said — so the rest of the run has only to
            // clear anything that would have contradicted it.
            if ( $home_id && ( 0 === $i || $rotates ) ) {
                $overrides[ $day ] = $home_id;
            } else {
                unset( $overrides[ $day ] );
            }
            $cursor = $cursor->modify( '+1 day' );
        }
        ksort( $overrides );

        update_post_meta( $person_id, self::META_OVERRIDES, $overrides );
        return true;
    }

    /**
     * Forget statements the calendar has moved past, so the list stays about
     * what is ahead — all but the last one made before today, which with no
     * pattern behind it is the statement still standing.
     */
    public static function prune_overrides( int $person_id ): void {
        $today = self::today();
        $overrides = self::get_overrides( $person_id );
        $standing = '';
        foreach ( $overrides as $date => $home_id ) {
            if ( (string) $date < $today ) {
                $standing = (string) $date;
            }
        }
        $kept = [];
        foreach ( $overrides as $date => $home_id ) {
            if ( (string) $date >= $today || (string) $date === $standing ) {
                $kept[ $date ] = $home_id;
            }
        }
        update_post_meta( $person_id, self::META_OVERRIDES, $kept );
    }

    /* ---------------------------------------------------------------- Lookups */

    /**
     * Which home a person is at on a date.
     *
     * @return array{home_id:int,is_override:bool,is_carried:bool}
     */
    public static function home_on( int $person_id, string $date ): array {
        $date = self::normalize_date( $date );
        if ( '' === $date ) {
            return [ 'home_id' => 0, 'is_override' => false, 'is_carried' => false ];
        }

        // What someone said about a day wins, and it stands on its own: a
        // statement about today needs no pattern behind it to be true.
        $overrides = self::get_overrides( $person_id );
        if ( isset( $overrides[ $date ] ) ) {
            return [ 'home_id' => $overrides[ $date ], 'is_override' => true, 'is_carried' => false ];
        }

        $rotation = self::get_rotation( $person_id );
        if ( $rotation ) {
            return [ 'home_id' => self::home_from_cycle( $rotation, $date ), 'is_override' => false, 'is_carried' => false ];
        }

        // With no pattern to answer for the day, the last thing said still
        // stands. Somebody who moved on Tuesday is still there on Wednesday:
        // people do not stop being anywhere, and a blank where they plainly
        // are is a worse answer than the one already given.
        $carried = self::standing_before( $overrides, $date );
        if ( $carried ) {
            return [ 'home_id' => $carried, 'is_override' => false, 'is_carried' => true ];
        }

        return [ 'home_id' => self::only_home( $person_id ), 'is_override' => false, 'is_carried' => false ];
    }

    /**
     * The home named by the last statement made before a date, or 0 when
     * nothing was said before it. Statements are kept oldest first, so the
     * last one that is not itself in the future is the one still standing.
     */
    private static function standing_before( array $overrides, string $date ): int {
        $home_id = 0;
        foreach ( $overrides as $said_on => $said_home ) {
            if ( (string) $said_on >= $date ) {
                break;
            }
            $home_id = (int) $said_home;
        }
        return $home_id;
    }

    /**
     * The home someone is at when nothing else says otherwise: the only one
     * they belong to, because there is nowhere else they could be.
     *
     * Belonging to several and rotating between none is not an answer, and is
     * not guessed at — it is asked about instead.
     */
    private static function only_home( int $person_id ): int {
        $homes = Access::home_ids_for_person( $person_id );
        return 1 === count( $homes ) ? $homes[0] : 0;
    }

    /**
     * A person's whereabouts over a run of days.
     *
     * A day nothing can account for has a home of 0. Not everyone rotates, and
     * not knowing is an answer worth returning: it is what lets a day be asked
     * about rather than quietly filled in.
     *
     * @return array[] one entry per day: date, home_id, is_override.
     */
    public static function days_for_person( int $person_id, string $start, int $days ): array {
        $rotation = self::get_rotation( $person_id );
        $overrides = self::get_overrides( $person_id );
        $only = $rotation ? 0 : self::only_home( $person_id );
        $cursor = \DateTimeImmutable::createFromFormat( '!Y-m-d', self::normalize_date( $start ) ?: self::today() );
        if ( ! $cursor ) {
            return [];
        }
        // A statement made before the window still stands on its first day, so
        // the board opens where the person actually is rather than at a blank.
        $carried = $rotation ? 0 : self::standing_before( $overrides, $cursor->format( 'Y-m-d' ) );
        $out = [];
        for ( $i = 0; $i < max( 1, $days ); $i++ ) {
            $date = $cursor->format( 'Y-m-d' );
            $said = isset( $overrides[ $date ] );
            if ( $said && ! $rotation ) {
                $carried = (int) $overrides[ $date ];
            }
            $out[] = [
                'date'        => $date,
                'home_id'     => $said ? $overrides[ $date ] : ( $rotation ? self::home_from_cycle( $rotation, $date ) : ( $carried ?: $only ) ),
                'is_override' => $said,
                'is_carried'  => ! $said && ! $rotation && (bool) $carried,
            ];
            $cursor = $cursor->modify( '+1 day' );
        }
        return $out;
    }

    /**
     * The day a person's current stay ends: the first date after `$from` on
     * which they are somewhere else.
     *
     * @return array{until:string,next_home_id:int}|array{} Empty when the stay
     *         runs past the horizon, or nothing says where they are now.
     */
    public static function stay_ends( int $person_id, string $from, int $horizon = 60 ): array {
        $days = self::days_for_person( $person_id, $from, $horizon );
        // Without a first day there is no stay to end.
        if ( ! $days || ! $days[0]['home_id'] ) {
            return [];
        }
        $current = $days[0]['home_id'];
        foreach ( $days as $day ) {
            if ( $day['home_id'] !== $current ) {
                return [ 'until' => $day['date'], 'next_home_id' => $day['home_id'] ];
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

    /** Homes in the order given, keeping only those the person still belongs to. */
    private static function filter_homes( int $person_id, array $homes ): array {
        return array_values( array_unique( array_filter(
            array_map( 'intval', $homes ),
            static function( int $home_id ) use ( $person_id ): bool {
                return Access::is_member( $person_id, $home_id );
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
