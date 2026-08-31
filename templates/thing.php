<?php
/**
 * One thing: what it is called, where it lives in the house, and which
 * household that house is. A thing is in one place at a time, so this page
 * moves it rather than taking it off a list it would then be on none of.
 */

namespace Households;

$hh_user = View::user_id();
$hh_thing = View::storage()->get_note( (int) get_query_var( 'note_id' ), Storage::ITEM );
$hh_reach = $hh_thing && Access::can_reach( $hh_user, $hh_thing['home_id'] );
$hh_writing = $hh_reach && current_user_can( 'organise_household', $hh_thing['home_id'] );
$hh_homes = $hh_reach ? View::storage()->get_homes_for_user( $hh_user ) : [];

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
        <p class="subtitle">
            <?php
            printf(
                /* translators: %s: the name of a household. */
                esc_html__( 'Kept at %s.', 'households' ),
                '<a href="' . esc_url( View::home_url( $hh_thing['home_id'] ) ) . '">' . esc_html( $hh_thing['home_name'] ) . '</a>'
            );
            ?>
        </p>
        <?php View::notice(); ?>

        <section>
            <?php if ( $hh_writing ) : ?>
                <form method="post" class="grid">
                    <?php View::fields( 'update_note', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_thing['home_id'] ] ); ?>
                    <label><?php echo esc_html__( 'Thing', 'households' ); ?>
                        <input type="text" name="title" required value="<?php echo esc_attr( $hh_thing['title'] ); ?>">
                    </label>
                    <label class="wide"><?php echo esc_html__( 'Where it lives', 'households' ); ?>
                        <input type="text" name="detail" value="<?php echo esc_attr( $hh_thing['detail'] ); ?>">
                    </label>
                    <button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button>
                </form>
            <?php else : ?>
                <p style="margin:0">
                    <?php
                    echo $hh_thing['detail']
                        ? esc_html( $hh_thing['detail'] )
                        : esc_html__( 'Nothing is written down about where it lives.', 'households' );
                    ?>
                </p>
            <?php endif; ?>
        </section>

        <?php if ( $hh_writing && count( $hh_homes ) > 1 ) : ?>
            <section>
                <h2><?php echo esc_html__( 'Move it', 'households' ); ?></h2>
                <?php // The thing names the household it is leaving, not the page, so this reads the same wherever it is asked from. ?>
                <form method="post" class="actions">
                    <?php View::fields( 'move_note', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_thing['home_id'] ] ); ?>
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
            </section>
        <?php endif; ?>

        <?php if ( $hh_writing ) : ?>
            <section>
                <div class="row heading">
                    <h2><?php echo esc_html__( 'Gone for good', 'households' ); ?></h2>
                    <form method="post">
                        <?php View::fields( 'remove_note', [ 'kind' => 'item', 'note_id' => $hh_thing['id'], 'home_id' => $hh_thing['home_id'] ] ); ?>
                        <button type="submit" class="quiet"><?php echo esc_html__( 'Remove', 'households' ); ?></button>
                    </form>
                </div>
                <p class="meta" style="margin:0"><?php echo esc_html__( 'For something the family no longer has. Something that has only gone to another house is moved instead.', 'households' ); ?></p>
            </section>
        <?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
