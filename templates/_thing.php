<?php
/**
 * One thing, and the households it could be moved to. A thing is somewhere
 * rather than nowhere, so it moves instead of being taken off the list.
 *
 * Expects $hh_thing, $hh_homes, and $hh_thing_said — true when a heading has
 * already said which household this is at, and the line need not say it again.
 */

namespace Households;

?>
<li class="row">
    <div class="grow">
        <strong><a href="<?php echo esc_url( View::thing_url( $hh_thing['id'] ) ); ?>"><?php echo esc_html( $hh_thing['title'] ); ?></a></strong>
        <?php if ( $hh_thing['detail'] ) : ?>
            <div class="meta"><?php echo esc_html( $hh_thing['detail'] ); ?></div>
        <?php endif; ?>
        <?php if ( ! $hh_thing_said ) : ?>
            <div style="margin-top:4px">
                <a class="pill" href="<?php echo esc_url( View::home_url( $hh_thing['home_id'] ) ); ?>">
                    <?php
                    /* translators: %s: the name of a household. */
                    echo esc_html( sprintf( __( 'at %s', 'households' ), $hh_thing['home_name'] ) );
                    ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php // The thing names the household it is leaving, not the page. ?>
    <form method="post" class="actions">
        <?php View::fields( 'move_note', [ 'home_id' => $hh_thing['home_id'], 'kind' => 'item', 'note_id' => $hh_thing['id'] ] ); ?>
        <?php foreach ( $hh_homes as $hh_other ) : ?>
            <?php if ( $hh_other['id'] !== $hh_thing['home_id'] ) : ?>
                <button type="submit" class="quiet" name="target_home_id" value="<?php echo (int) $hh_other['id']; ?>">
                    <?php
                    /* translators: %s: the name of a household. */
                    echo esc_html( sprintf( __( 'Move to %s', 'households' ), $hh_other['name'] ) );
                    ?>
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </form>
</li>
