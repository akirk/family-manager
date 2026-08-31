<?php
/**
 * One thing on a list: where it lives, and which households keep it.
 *
 * Expects $hh_thing, $hh_homes, and $hh_thing_home — the household whose list
 * this is, or 0 when the list is of everything and no heading has said. Under a
 * household the line says where in that house it lives; across them it names
 * every household of yours that keeps it.
 */

namespace Households;

// Under a household's heading the line says where in that house it lives, and
// names the others only to say that they have it too. Read across households
// nothing has been said yet, so each one says its own name and its own answer.
$hh_where = '';
$hh_at = [];
foreach ( $hh_thing['homes'] as $hh_one ) {
    if ( $hh_one['id'] === $hh_thing_home ) {
        $hh_where = $hh_one['where'];
    } else {
        $hh_at[] = $hh_one;
    }
}

// Moving is one household handing it to another, so it is offered only where
// there is one household it would be leaving: the list's own, or the only one
// keeping it. It cannot be handed to a house that already has it.
$hh_source = $hh_thing_home ? $hh_thing_home : ( 1 === count( $hh_thing['homes'] ) ? $hh_thing['homes'][0]['id'] : 0 );
$hh_targets = [];
if ( $hh_source && current_user_can( 'organise_household', $hh_source ) ) {
    $hh_keeping = wp_list_pluck( $hh_thing['homes'], 'id' );
    foreach ( $hh_homes as $hh_other ) {
        if ( ! in_array( $hh_other['id'], $hh_keeping, true ) ) {
            $hh_targets[] = $hh_other;
        }
    }
}
?>
<li class="row">
    <div class="grow">
        <strong><a href="<?php echo esc_url( View::thing_url( $hh_thing['id'] ) ); ?>"><?php echo esc_html( $hh_thing['title'] ); ?></a></strong>
        <?php if ( $hh_thing_home ) : ?>
            <?php if ( $hh_where ) : ?>
                <div class="meta"><?php echo esc_html( $hh_where ); ?></div>
            <?php endif; ?>
            <?php if ( $hh_at ) : ?>
                <div class="actions" style="margin-top:4px">
                    <?php foreach ( $hh_at as $hh_one ) : ?>
                        <a class="pill" href="<?php echo esc_url( View::home_url( $hh_one['id'] ) ); ?>">
                            <?php
                            /* translators: %s: the name of a household. */
                            echo esc_html( sprintf( __( 'also at %s', 'households' ), $hh_one['name'] ) );
                            ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <?php foreach ( $hh_at as $hh_one ) : ?>
                <div class="actions" style="margin-top:4px">
                    <a class="pill" href="<?php echo esc_url( View::home_url( $hh_one['id'] ) ); ?>">
                        <?php
                        /* translators: %s: the name of a household. */
                        echo esc_html( sprintf( __( 'at %s', 'households' ), $hh_one['name'] ) );
                        ?>
                    </a>
                    <?php if ( $hh_one['where'] ) : ?>
                        <span class="meta"><?php echo esc_html( $hh_one['where'] ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php // The thing names the household it is leaving, not the page, so this reads the same wherever it is asked from. ?>
    <?php if ( $hh_targets ) : ?>
        <form method="post" class="actions">
            <?php View::fields( 'move_note', [ 'home_id' => $hh_source, 'kind' => 'item', 'note_id' => $hh_thing['id'] ] ); ?>
            <?php foreach ( $hh_targets as $hh_other ) : ?>
                <button type="submit" class="quiet" name="target_home_id" value="<?php echo (int) $hh_other['id']; ?>">
                    <?php
                    /* translators: %s: the name of a household. */
                    echo esc_html( sprintf( __( 'Move to %s', 'households' ), $hh_other['name'] ) );
                    ?>
                </button>
            <?php endforeach; ?>
        </form>
    <?php endif; ?>
</li>
