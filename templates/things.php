<?php
/**
 * Everything kept across the households you belong to, and which one it is at.
 *
 * A thing is in one place at a time, so read house by house it is under one
 * heading and one only: the house it is at. That several houses have a place
 * for it — the hook by the door at one, the kitchen drawer at the other — is
 * true of the houses rather than of the thing, and is said on the thing's own
 * page, where those places are written. A list of things that showed the same
 * thing twice would be saying it is in both.
 *
 * Read as one list, nothing has been said by a heading, so each thing names
 * every house of yours that has a place for it, and where it is now.
 */

namespace Households;

$hh_user = View::user_id();
$hh_things = View::storage()->get_things_overview( $hh_user );
$hh_homes = View::storage()->get_homes_for_user( $hh_user );

// Read house by house, or as one list of everything there is. Which of the two
// is in the URL, so it survives a move and can be passed to somebody else.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$hh_flat = ! empty( $_GET['flat'] );
$hh_url = remove_query_arg( 'problem' );

// Grouped, the household is a heading and every thing under it says where in
// that house it lives, because the heading already said which house. Which
// heading a thing is under is where it is, not which houses have a place for
// it: it is in one place at a time, so it is under one heading at a time.
$hh_groups = [];
// The two answers no household of yours can be a heading for: a thing taken
// somewhere that is not yours, and a thing nobody has said anything about.
// They are lists of their own rather than a thing left off the page.
$hh_elsewhere = [];
$hh_unsaid = [];
if ( ! $hh_flat ) {
    foreach ( $hh_homes as $hh_home ) {
        $hh_groups[ $hh_home['id'] ] = [ 'name' => $hh_home['name'], 'things' => [] ];
    }
    foreach ( $hh_things as $hh_thing ) {
        $hh_at = ! empty( $hh_thing['at']['home_id'] ) ? (int) $hh_thing['at']['home_id'] : 0;
        if ( isset( $hh_groups[ $hh_at ] ) ) {
            $hh_groups[ $hh_at ]['things'][] = $hh_thing;
        } elseif ( $hh_at ) {
            $hh_elsewhere[] = $hh_thing;
        } else {
            $hh_unsaid[] = $hh_thing;
        }
    }
}

// A thing is added here as it is on a household's own page; all that is missing
// across households is which one it is at, so that is the one extra field. Only
// the households you write in are offered, because the others would refuse it.
$hh_writable = [];
foreach ( $hh_homes as $hh_home ) {
    if ( current_user_can( 'organise_household', $hh_home['id'] ) ) {
        $hh_writable[] = $hh_home;
    }
}
$hh_default_home = View::storage()->last_home_id( $hh_user );

$hh_title = __( 'Things', 'households' );

require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Overview', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Things', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Everything kept across the households you belong to, and which one it is at.', 'households' ); ?></p>
        <?php View::notice(); ?>

        <?php // What can be done to this page — read it house by house, or add something to it — is the page's own rather than the list's, so it stays put while the list comes and goes. ?>
        <div class="actions" style="margin-bottom:12px">
            <a class="pill<?php echo $hh_flat ? '' : ' on'; ?>"
                href="<?php echo esc_url( $hh_flat ? remove_query_arg( 'flat', $hh_url ) : add_query_arg( 'flat', 1, $hh_url ) ); ?>">
                <?php echo esc_html__( 'by household', 'households' ); ?>
            </a>
            <?php if ( $hh_writable ) : ?>
            <details class="add">
                <summary><?php echo esc_html__( '+ Add', 'households' ); ?></summary>
                <form method="post" class="grid">
                    <?php View::fields( 'add_note', [ 'kind' => 'item' ] ); ?>
                    <label><?php echo esc_html__( 'Thing', 'households' ); ?>
                        <input type="text" name="title" required>
                    </label>
                    <label><?php echo esc_html__( 'Where it lives', 'households' ); ?>
                        <input type="text" name="detail">
                    </label>
                    <?php // One household to keep it in is not a question; the form says which it is and asks nothing. ?>
                    <?php if ( count( $hh_writable ) > 1 ) : ?>
                        <label><?php echo esc_html__( 'At which household', 'households' ); ?>
                            <select name="home_id">
                                <?php foreach ( $hh_writable as $hh_home ) : ?>
                                    <option value="<?php echo (int) $hh_home['id']; ?>" <?php selected( $hh_home['id'], $hh_default_home ); ?>>
                                        <?php echo esc_html( $hh_home['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php else : ?>
                        <input type="hidden" name="home_id" value="<?php echo (int) $hh_writable[0]['id']; ?>">
                    <?php endif; ?>
                    <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                </form>
            </details>
            <?php endif; ?>
        </div>

        <section id="hh-things" data-hh-live-section>
            <?php require __DIR__ . '/_undone.php'; ?>

            <?php if ( ! $hh_things ) : ?>
                <ul class="plain">
                    <li class="empty"><?php echo esc_html__( 'Nothing listed in any of your households yet.', 'households' ); ?></li>
                </ul>
            <?php elseif ( $hh_flat ) : ?>
                <ul class="plain">
                    <?php // Read as one list, no household is the one you are standing in, so this is a page for finding a thing rather than for saying anything about it. ?>
                    <?php foreach ( $hh_things as $hh_thing ) : ?>
                        <?php $hh_thing_home = 0; ?>
                        <?php $hh_thing_at_said = false; ?>
                        <?php $hh_thing_writing = false; ?>
                        <?php $hh_thing_going_said = false; ?>
                        <?php require __DIR__ . '/_thing.php'; ?>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <?php foreach ( $hh_groups as $hh_group_id => $hh_group ) : ?>
                    <h3 style="margin:14px 0 6px;font-size:0.95rem">
                        <a href="<?php echo esc_url( View::home_url( $hh_group_id ) ); ?>"><?php echo esc_html( $hh_group['name'] ); ?></a>
                    </h3>
                    <ul class="plain">
                        <?php if ( ! $hh_group['things'] ) : ?>
                            <li class="meta"><?php echo esc_html__( 'Nothing listed here.', 'households' ); ?></li>
                        <?php endif; ?>
                        <?php foreach ( $hh_group['things'] as $hh_thing ) : ?>
                            <?php $hh_thing_home = $hh_group_id; ?>
                            <?php $hh_thing_at_said = true; ?>
                            <?php $hh_thing_writing = true; ?>
                            <?php $hh_thing_going_said = false; ?>
                            <?php require __DIR__ . '/_thing.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>

                <?php // No heading of yours can be put over these, so they say for themselves what is known about where they are. ?>
                <?php foreach ( [ $hh_elsewhere, $hh_unsaid ] as $hh_loose_which => $hh_loose ) : ?>
                    <?php if ( $hh_loose ) : ?>
                        <h3 style="margin:14px 0 6px;font-size:0.95rem">
                            <?php
                            echo $hh_loose_which
                                ? esc_html__( 'Nobody has said where these are', 'households' )
                                : esc_html__( 'Somewhere that is not yours', 'households' );
                            ?>
                        </h3>
                        <ul class="plain">
                            <?php foreach ( $hh_loose as $hh_thing ) : ?>
                                <?php $hh_thing_home = 0; ?>
                                <?php $hh_thing_at_said = true; ?>
                                <?php $hh_thing_writing = true; ?>
                                <?php $hh_thing_going_said = false; ?>
                                <?php require __DIR__ . '/_thing.php'; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>
<?php require __DIR__ . '/_todo-script.php'; ?>
<?php require __DIR__ . '/_foot.php'; ?>
