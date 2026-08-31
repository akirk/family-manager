<?php
/**
 * One thing on a list: where it lives, which households keep it, and where it
 * has got to if that is somewhere other than where the list is being read.
 *
 * Expects $hh_thing and $hh_thing_home — the household whose list this is, or 0
 * when the list is of everything and no heading has said. Under a household the
 * line says where in that house it lives; across them it names every household
 * of yours that keeps it.
 */

namespace Households;

// Under a household's heading the line says where in that house it lives, and
// names the others only to say that they have it too. Read across households
// nothing has been said yet, so each one says its own name and its own answer.
$hh_where = '';
$hh_also = [];
foreach ( $hh_thing['homes'] as $hh_one ) {
    if ( $hh_one['id'] === $hh_thing_home ) {
        $hh_where = $hh_one['where'];
    } else {
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

// Saying it is back is said where you are standing: on a household's own list,
// about that household, and only when the thing is not already there.
$hh_here_now = $hh_thing_home
    && ( empty( $hh_at['home_id'] ) || $hh_at['home_id'] !== $hh_thing_home )
    && current_user_can( 'organise_household', $hh_thing_home );
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
    </div>
    <?php // Where it has got to is not where it belongs, so saying it is here leaves every line about where it lives as it was. ?>
    <?php if ( $hh_here_now ) : ?>
        <form method="post">
            <?php View::fields( 'note_is_at', [ 'home_id' => $hh_thing_home, 'kind' => 'item', 'note_id' => $hh_thing['id'] ] ); ?>
            <button type="submit" class="quiet"><?php echo esc_html__( 'It is here now', 'households' ); ?></button>
        </form>
    <?php endif; ?>
</li>
