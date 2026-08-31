<?php
/**
 * Everything kept across the households you belong to, and which of them keep
 * it. A thing can be kept at more than one, so read house by house it appears
 * under each, and read as one list it appears once and names them.
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
// that house it lives, because the heading already said which house.
$hh_groups = [];
if ( ! $hh_flat ) {
    foreach ( $hh_homes as $hh_home ) {
        $hh_groups[ $hh_home['id'] ] = [ 'name' => $hh_home['name'], 'things' => [] ];
    }
    foreach ( $hh_things as $hh_thing ) {
        foreach ( $hh_thing['homes'] as $hh_at ) {
            if ( isset( $hh_groups[ $hh_at['id'] ] ) ) {
                $hh_groups[ $hh_at['id'] ]['things'][] = $hh_thing;
            }
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

        <section>
            <div class="row heading">
                <h2><?php echo esc_html__( 'Everything kept', 'households' ); ?></h2>
                <div class="actions">
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
            </div>

            <?php if ( ! $hh_things ) : ?>
                <ul class="plain">
                    <li class="empty"><?php echo esc_html__( 'Nothing listed in any of your households yet.', 'households' ); ?></li>
                </ul>
            <?php elseif ( $hh_flat ) : ?>
                <ul class="plain">
                    <?php foreach ( $hh_things as $hh_thing ) : ?>
                        <?php $hh_thing_home = 0; ?>
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
                            <?php require __DIR__ . '/_thing.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>
<?php require __DIR__ . '/_foot.php'; ?>
