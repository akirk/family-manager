<?php

namespace Households;

/**
 * What the templates need to render themselves.
 *
 * The pages are ordinary PHP: they read through `Storage`, print HTML, and
 * post their changes back to their own URL. This holds the few things every
 * one of them needs — the shared storage, the URLs, the hidden fields that
 * carry a verb and its nonce, and the notice a refused write leaves behind.
 */
class View {
    /** @var Storage|null */
    private static $storage = null;

    public static function storage(): Storage {
        if ( ! self::$storage ) {
            self::$storage = new Storage();
        }
        return self::$storage;
    }

    public static function user_id(): int {
        return get_current_user_id();
    }

    /** The person the viewer is, or 0 for an account with no record. */
    public static function person_id(): int {
        return Access::person_for_user( get_current_user_id() );
    }

    public static function base(): string {
        return home_url( '/households/' );
    }

    public static function home_url( int $home_id, string $suffix = '' ): string {
        return self::base() . $home_id . '/' . $suffix;
    }

    public static function person_url( int $person_id ): string {
        return self::base() . 'person/' . $person_id . '/';
    }

    public static function thing_url( int $note_id ): string {
        return self::base() . 'thing/' . $note_id . '/';
    }

    /** A date as the app says it out loud: today, tomorrow, or "Fri 4 Sep". */
    public static function date( string $date ): string {
        return self::storage()->say_date( $date );
    }

    /**
     * A moment something was written down: the day, and the time after it.
     * WordPress keeps these in the site's own time, and reads them back so.
     */
    public static function when( string $stamp ): string {
        return $stamp ? mysql2date( 'D j M Y, H:i', $stamp ) : '';
    }

    /**
     * The hidden fields every form carries: which verb it is, and the nonce
     * that says the form came from here. The nonce is per verb, so a form for
     * one thing cannot be replayed as another.
     */
    public static function fields( string $action, array $hidden = [] ): void {
        echo '<input type="hidden" name="household_action" value="' . esc_attr( $action ) . '">';
        foreach ( $hidden as $name => $value ) {
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
        }
        wp_nonce_field( 'households_' . $action );
    }

    /** What a refused or impossible write left in the URL, said out loud. */
    public static function notice(): void {
        // Read for display only; the write it refers to has already happened
        // or been refused, and was itself checked against a nonce.
        $problem = isset( $_GET['problem'] ) ? sanitize_key( wp_unslash( $_GET['problem'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( '' === $problem ) {
            return;
        }
        $said = [
            'not-allowed' => __( 'You are not allowed to do that.', 'households' ),
            'expired'     => __( 'That form had been open too long. Nothing was changed — try again.', 'households' ),
            'no-home'     => __( 'You do not have a household yet.', 'households' ),
            'no-name'     => __( 'That needs a name.', 'households' ),
        ];
        printf(
            '<p class="status" data-error="1">%s</p>',
            esc_html( isset( $said[ $problem ] ) ? $said[ $problem ] : $said['not-allowed'] )
        );
    }
}
