<?php
/**
 * What can be said about where a thing is to get to, wherever it is read.
 *
 * Where it is and where it is to go are two marks on the thing, and saying the
 * second moves nothing: it is where it was until somebody says it has got
 * there. That is the same question on a row in a list, on the thing's own page
 * and on the packlist, so it is asked once, here, and the pages differ only in
 * how they say where the thing is now.
 *
 * Expects $hh_going_note — the thing; $hh_going_going — where it is to get to,
 * as the storage says it, or nothing; $hh_going_targets — the households of
 * yours it could be sent to; $hh_going_here — the household whose list this is,
 * or 0; and $hh_going_writing, whether this page is in a mood to offer writing
 * at all, which is not only a matter of permission, since a household being
 * read as somebody else is being read rather than organised.
 */

namespace Households;

$hh_going_to = ! empty( $hh_going_going['home_id'] ) ? (int) $hh_going_going['home_id'] : 0;
// Both of the answers to a thing that is going somewhere are answers about the
// household it is going to — it has got there, or it is not coming after all —
// so both are asked of whoever writes in that household, exactly as saying it
// was going there was. Somebody who does not is left the line and no buttons.
if ( $hh_going_to && ! current_user_can( 'organise_household', $hh_going_to ) ) {
    return;
}
if ( ! $hh_going_writing || ( ! $hh_going_to && ! $hh_going_targets ) ) {
    return;
}
?>
<div class="actions">
    <?php if ( $hh_going_to ) : ?>
        <?php // In the bag is as good as there: from here on, the house it was going to is where to look for it. Read at that household it is not a bag being packed but a thing arriving, which is what every other list here calls it. ?>
        <form method="post" class="inline">
            <?php View::fields( 'note_is_at', [ 'kind' => 'item', 'note_id' => $hh_going_note, 'home_id' => $hh_going_to ] ); ?>
            <button type="submit" class="quiet">
                <?php
                echo $hh_going_here === $hh_going_to
                    ? esc_html__( 'It is here now', 'households' )
                    : esc_html__( 'Packed', 'households' );
                ?>
            </button>
        </form>
        <?php // Called off, which asks nothing of where it is or where it lives. ?>
        <form method="post" class="inline">
            <?php View::fields( 'note_not_going', [ 'kind' => 'item', 'note_id' => $hh_going_note, 'home_id' => $hh_going_to ] ); ?>
            <button type="submit" class="quiet"><?php echo esc_html__( 'Not going', 'households' ); ?></button>
        </form>
    <?php else : ?>
        <?php // The household is the button rather than the form, so one nonce answers for the whole row of them. ?>
        <form method="post" class="actions">
            <?php View::fields( 'note_goes_to', [ 'kind' => 'item', 'note_id' => $hh_going_note ] ); ?>
            <?php foreach ( $hh_going_targets as $hh_going_other ) : ?>
                <button type="submit" class="quiet" name="home_id" value="<?php echo (int) $hh_going_other['id']; ?>">
                    <?php
                    /* translators: %s: the name of a household. */
                    echo esc_html( sprintf( __( 'To go to %s', 'households' ), $hh_going_other['name'] ) );
                    ?>
                </button>
            <?php endforeach; ?>
        </form>
    <?php endif; ?>
</div>
