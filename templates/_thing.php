<?php
/**
 * One thing on a list: where it lives, which households keep it, where it has
 * got to if that is somewhere other than where the list is being read, and
 * where it is to get to if it is on its way anywhere.
 *
 * Expects $hh_thing; $hh_homes, the households of the viewer's; and
 * $hh_thing_home — the household whose list this is, or 0 when the list is of
 * everything and no heading has said. Under a household the line says where in
 * that house it lives; across them it names every household of yours that keeps
 * it. $hh_thing_writing says whether this page offers anything to be said at
 * all — a household read as somebody else is being read rather than organised,
 * and a list on the overview is a shelf being looked at. $hh_thing_going_said
 * is for a heading that is itself a trip and has named where it is going.
 */

namespace Households;

// Under a household's heading the line says where in that house it lives, and
// names the others only to say that they have it too. Read across households
// nothing has been said yet, so each one says its own name and its own answer.
// A household nobody in the room belongs to is never named.
$hh_where = '';
$hh_also = [];
foreach ( $hh_thing['homes'] as $hh_one ) {
    if ( $hh_one['id'] === $hh_thing_home ) {
        $hh_where = $hh_one['where'];
    } elseif ( Access::can_reach( View::user_id(), $hh_one['id'] ) ) {
        $hh_also[] = $hh_one;
    }
}

// Where it is at this moment, said only where that adds something. Under a
// household's heading it is worth saying whenever the thing is not there. Read
// as one list, a thing kept in one house and in it says nothing new; a thing
// taken elsewhere, or kept in several and said to be in one of them, does.
// A household of somebody else's is not named, only that it is not one of
// yours.
$hh_at = ! empty( $hh_thing['at'] ) ? $hh_thing['at'] : [];
$hh_at_away = ! empty( $hh_at['home_id'] ) && $hh_at['home_id'] !== $hh_thing_home;
$hh_at_worth = $hh_at_away && ( $hh_thing_home || ! $hh_at['kept'] || count( $hh_thing['homes'] ) > 1 );
$hh_at_named = $hh_at_worth && Access::can_reach( View::user_id(), $hh_at['home_id'] );

// Where it is to get to, and the bag it is waiting in. A heading that is itself
// a trip has said it already, and a household of somebody else's is not named
// here any more than anywhere else.
$hh_going = ! empty( $hh_thing['going'] ) ? $hh_thing['going'] : [];
$hh_going_from = ! empty( $hh_at['home_id'] ) ? $hh_at['home_id'] : $hh_thing_home;
$hh_going_named = ! empty( $hh_going['home_id'] )
    && empty( $hh_thing_going_said )
    && Access::can_reach( View::user_id(), $hh_going['home_id'] );

// Saying it is back is said where you are standing: on a household's own list,
// about that household, and only when the thing is not already there and is not
// on its way anywhere, which is a sentence with its own answers.
$hh_here_now = $hh_thing_writing
    && $hh_thing_home
    && empty( $hh_going['home_id'] )
    && ( empty( $hh_at['home_id'] ) || $hh_at['home_id'] !== $hh_thing_home )
    && current_user_can( 'organise_household', $hh_thing_home );

// Where it could be sent: your households, less the one it is already at. Only
// offered where the thing is, because a shelf you are not standing at is not
// one you are packing from.
$hh_going_targets = [];
if ( $hh_thing_writing && $hh_thing_home && $hh_going_from === $hh_thing_home ) {
    foreach ( $hh_homes as $hh_one ) {
        if ( $hh_one['id'] !== $hh_thing_home && current_user_can( 'organise_household', $hh_one['id'] ) ) {
            $hh_going_targets[] = $hh_one;
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
            <?php if ( $hh_also ) : ?>
                <div class="actions" style="margin-top:4px">
                    <?php foreach ( $hh_also as $hh_one ) : ?>
                        <a class="pill" href="<?php echo esc_url( View::home_url( $hh_one['id'] ) ); ?>">
                            <?php
                            /* translators: %s: the name of a household. */
                            echo esc_html( sprintf( __( 'also kept at %s', 'households' ), $hh_one['name'] ) );
                            ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <?php foreach ( $hh_also as $hh_one ) : ?>
                <div class="actions" style="margin-top:4px">
                    <a class="pill" href="<?php echo esc_url( View::home_url( $hh_one['id'] ) ); ?>">
                        <?php
                        /* translators: %s: the name of a household. */
                        echo esc_html( sprintf( __( 'kept at %s', 'households' ), $hh_one['name'] ) );
                        ?>
                    </a>
                    <?php if ( $hh_one['where'] ) : ?>
                        <span class="meta"><?php echo esc_html( $hh_one['where'] ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ( $hh_at_named ) : ?>
            <div class="meta">
                <?php
                /* translators: %s: the name of a household. */
                echo esc_html( sprintf( __( 'It is at %s just now.', 'households' ), $hh_at['name'] ) );
                ?>
            </div>
        <?php elseif ( $hh_at_worth ) : ?>
            <div class="meta"><?php echo esc_html__( 'It is not at any of your households just now.', 'households' ); ?></div>
        <?php endif; ?>
        <?php if ( $hh_going_named && $hh_going['home_id'] === $hh_thing_home ) : ?>
            <?php // Read at the household it is coming to, the household to name is this one, which the heading has said. ?>
            <div class="meta">
                <a href="<?php echo esc_url( View::pack_url( $hh_going_from, $hh_going['home_id'] ) ); ?>"><?php echo esc_html__( 'It is on its way here.', 'households' ); ?></a>
            </div>
        <?php elseif ( $hh_going_named ) : ?>
            <div class="meta">
                <?php
                printf(
                    /* translators: %s: a link naming a household, and what else is going there. */
                    esc_html__( 'It is to go to %s.', 'households' ),
                    '<a href="' . esc_url( View::pack_url( $hh_going_from, $hh_going['home_id'] ) ) . '">' . esc_html( $hh_going['name'] ) . '</a>'
                );
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php // Where it has got to is not where it belongs, so saying it is here leaves every line about where it lives as it was. ?>
    <?php if ( $hh_here_now ) : ?>
        <form method="post">
            <?php View::fields( 'note_is_at', [ 'home_id' => $hh_thing_home, 'kind' => 'item', 'note_id' => $hh_thing['id'] ] ); ?>
            <button type="submit" class="quiet"><?php echo esc_html__( 'It is here now', 'households' ); ?></button>
        </form>
    <?php endif; ?>
    <?php
    $hh_going_note = $hh_thing['id'];
    $hh_going_going = $hh_going;
    $hh_going_here = $hh_thing_home;
    $hh_going_writing = $hh_thing_writing;
    ?>
    <?php require __DIR__ . '/_going.php'; ?>
</li>
