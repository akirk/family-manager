<?php
/**
 * One thing: what it is called, and where it lives in each of the houses that
 * keep it. A thing can be in more than one household — a charger, a spare key,
 * a set of wellies at each grandparent's — and it is in a different place in
 * each, so where it lives is asked once per household rather than once.
 */

namespace Households;

$hh_user = View::user_id();
$hh_thing = View::storage()->get_note( (int) get_query_var( 'note_id' ), Storage::ITEM );

// Only the households that are yours. That another family keeps the same thing
// is not yours to be told, so their line is not on this page at all.
$hh_kept = [];
foreach ( $hh_thing ? $hh_thing['homes'] : [] as $hh_one ) {
    if ( Access::can_reach( $hh_user, $hh_one['id'] ) ) {
        $hh_one['writing'] = current_user_can( 'organise_household', $hh_one['id'] );
        $hh_kept[] = $hh_one;
    }
}
$hh_reach = (bool) $hh_kept;

// The name, the note and the deleting are the thing's own rather than any one
// household's, so they are signed for by the first household that keeps it and
// that you write in — and are not offered at all if there is none.
$hh_writing = 0;
foreach ( $hh_kept as $hh_one ) {
    if ( $hh_one['writing'] ) {
        $hh_writing = $hh_one['id'];
        break;
    }
}

// Every wording the note has had, newest first. A save that left it alone is
// not a version of it, so it is not one here either.
$hh_history = $hh_reach ? View::storage()->get_note_history( $hh_thing['id'], Storage::ITEM ) : [];

// The households of yours that could keep it too: the ones you write in that
// are not already on the list above.
$hh_elsewhere = [];
if ( $hh_reach ) {
    $hh_already = wp_list_pluck( $hh_thing['homes'], 'id' );
    foreach ( View::storage()->get_homes_for_user( $hh_user ) as $hh_other ) {
        if ( ! in_array( $hh_other['id'], $hh_already, true ) && current_user_can( 'organise_household', $hh_other['id'] ) ) {
            $hh_elsewhere[] = $hh_other;
        }
    }
}

$hh_title = $hh_reach ? $hh_thing['title'] : __( 'Thing', 'households' );

require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( View::base() . 'things/' ); ?>">&larr; <?php echo esc_html__( 'Things', 'households' ); ?></a>

        <?php if ( ! $hh_reach ) : ?>
            <?php // Someone else's house, or nothing at all: the same answer either way, because which of the two it is is not yours to learn. ?>
            <h1><?php echo esc_html__( 'Thing', 'households' ); ?></h1>
            <section><p class="empty"><?php echo esc_html__( 'That is not something you can look at.', 'households' ); ?></p></section>
            <?php require __DIR__ . '/_foot.php'; ?>
            <?php return; ?>
        <?php endif; ?>

        <h1><?php echo esc_html( $hh_thing['title'] ); ?></h1>
        <?php View::notice(); ?>

        <section>
            <?php if ( $hh_writing ) : ?>
                <?php // Where it lives is not asked here: it is asked of each household below, and a form that does not carry it leaves it alone. ?>
                <form method="post" class="grid">
                    <?php View::fields( 'update_note', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_writing ] ); ?>
                    <label class="wide"><?php echo esc_html__( 'Thing', 'households' ); ?>
                        <input type="text" name="title" required value="<?php echo esc_attr( $hh_thing['title'] ); ?>">
                    </label>
                    <label class="wide"><?php echo esc_html__( 'Note', 'households' ); ?>
                        <textarea name="note"><?php echo esc_textarea( $hh_thing['note'] ); ?></textarea>
                    </label>
                    <button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button>
                </form>
            <?php elseif ( $hh_thing['note'] ) : ?>
                <p class="note" style="margin:0"><?php echo esc_html( $hh_thing['note'] ); ?></p>
            <?php else : ?>
                <p class="meta" style="margin:0"><?php echo esc_html__( 'Nothing is written down about it.', 'households' ); ?></p>
            <?php endif; ?>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Where it lives', 'households' ); ?></h2>
            <ul class="plain">
                <?php foreach ( $hh_kept as $hh_one ) : ?>
                    <li class="row">
                        <div class="grow">
                            <strong><a href="<?php echo esc_url( View::home_url( $hh_one['id'] ) ); ?>"><?php echo esc_html( $hh_one['name'] ); ?></a></strong>
                            <?php if ( $hh_one['writing'] ) : ?>
                                <?php // The household the line is about is the household the form names, so it is that one's permission that is asked for. ?>
                                <form method="post" class="row" style="margin-top:6px">
                                    <?php View::fields( 'keep_note_at', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_one['id'] ] ); ?>
                                    <input class="grow" type="text" name="where"
                                        value="<?php echo esc_attr( $hh_one['where'] ); ?>"
                                        aria-label="<?php
                                        /* translators: %s: the name of a household. */
                                        echo esc_attr( sprintf( __( 'Where it lives at %s', 'households' ), $hh_one['name'] ) );
                                        ?>">
                                    <button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button>
                                </form>
                            <?php else : ?>
                                <div class="meta">
                                    <?php
                                    echo $hh_one['where']
                                        ? esc_html( $hh_one['where'] )
                                        : esc_html__( 'Nothing is written down about where it lives.', 'households' );
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php // The last household is not offered: a thing kept nowhere is one to delete, which is said in one line at the foot of the page. ?>
                        <?php if ( $hh_one['writing'] && count( $hh_thing['homes'] ) > 1 ) : ?>
                            <form method="post">
                                <?php View::fields( 'drop_note_at', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_one['id'] ] ); ?>
                                <button type="submit" class="quiet"><?php echo esc_html__( 'Not kept here', 'households' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ( $hh_elsewhere ) : ?>
                <details class="add">
                    <summary><?php echo esc_html__( '+ Kept somewhere else too', 'households' ); ?></summary>
                    <form method="post" class="grid">
                        <?php View::fields( 'keep_note_at', [ 'kind' => 'item', 'note_id' => $hh_thing['id'] ] ); ?>
                        <?php // One household to add it to is not a question; the form says which it is and asks nothing. ?>
                        <?php if ( count( $hh_elsewhere ) > 1 ) : ?>
                            <label><?php echo esc_html__( 'At which household', 'households' ); ?>
                                <select name="home_id">
                                    <?php foreach ( $hh_elsewhere as $hh_other ) : ?>
                                        <option value="<?php echo (int) $hh_other['id']; ?>"><?php echo esc_html( $hh_other['name'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php else : ?>
                            <input type="hidden" name="home_id" value="<?php echo (int) $hh_elsewhere[0]['id']; ?>">
                        <?php endif; ?>
                        <label>
                            <?php
                            echo 1 === count( $hh_elsewhere )
                                /* translators: %s: the name of a household. */
                                ? esc_html( sprintf( __( 'Where it lives at %s', 'households' ), $hh_elsewhere[0]['name'] ) )
                                : esc_html__( 'Where it lives', 'households' );
                            ?>
                            <input type="text" name="where">
                        </label>
                        <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                    </form>
                </details>
            <?php endif; ?>
        </section>

        <?php if ( $hh_history ) : ?>
            <section>
                <h2><?php echo esc_html__( 'What the note said before', 'households' ); ?></h2>
                <ul class="plain">
                    <?php foreach ( $hh_history as $hh_was ) : ?>
                        <li class="row">
                            <div class="grow">
                                <div class="meta">
                                    <?php
                                    echo esc_html( View::when( $hh_was['when'] ) );
                                    echo $hh_was['who'] ? ' · ' . esc_html( $hh_was['who'] ) : '';
                                    ?>
                                </div>
                                <?php if ( $hh_was['note'] ) : ?>
                                    <p class="note"><?php echo esc_html( $hh_was['note'] ); ?></p>
                                <?php else : ?>
                                    <p class="note meta"><?php echo esc_html__( 'Nothing was written down.', 'households' ); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php // Only the note goes back. The name and where it lives are what they are now, and were not what was asked about. ?>
                            <?php if ( $hh_writing ) : ?>
                                <form method="post">
                                    <?php View::fields( 'restore_note', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_writing, 'revision_id' => $hh_was['id'] ] ); ?>
                                    <button type="submit" class="quiet"><?php echo esc_html__( 'Put this back', 'households' ); ?></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ( $hh_writing ) : ?>
            <?php // For something the family no longer has anywhere. Something that has only stopped being kept at one house is taken off that house's line above, so this is one line and not a box asking to be read. ?>
            <form method="post" style="margin-bottom:16px">
                <?php View::fields( 'remove_note', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_writing ] ); ?>
                <button type="submit" class="quiet"><?php echo esc_html__( 'Delete this thing', 'households' ); ?></button>
            </form>
        <?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
