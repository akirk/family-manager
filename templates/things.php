<?php
/**
 * Everything kept across the homes you belong to, and which house it is at.
 * A thing is in one place at a time, so it is moved rather than removed.
 */

namespace Households;

$hh_user = View::user_id();
$hh_person = View::person_id();
$hh_things = View::storage()->get_things_overview( $hh_user );
$hh_homes = View::storage()->get_homes_for_person( $hh_person );

require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Your day', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Things', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Everything kept across the homes you belong to, and which house it is at.', 'households' ); ?></p>
        <?php View::notice(); ?>

        <section>
            <ul class="plain">
                <?php if ( ! $hh_things ) : ?>
                    <li class="empty"><?php echo esc_html__( 'Nothing listed in any of your homes yet.', 'households' ); ?></li>
                <?php endif; ?>
                <?php foreach ( $hh_things as $hh_thing ) : ?>
                    <li class="row">
                        <div class="grow">
                            <strong><?php echo esc_html( $hh_thing['title'] ); ?></strong>
                            <div class="meta"><?php echo esc_html( $hh_thing['detail'] ); ?></div>
                            <div style="margin-top:4px">
                                <a class="pill" style="text-decoration:none" href="<?php echo esc_url( View::home_url( $hh_thing['home_id'] ) ); ?>">
                                    <?php
                                    /* translators: %s: the name of a home. */
                                    echo esc_html( sprintf( __( 'at %s', 'households' ), $hh_thing['home_name'] ) );
                                    ?>
                                </a>
                            </div>
                        </div>
                        <?php // The thing names the home it is leaving, not the page. ?>
                        <form method="post" class="actions">
                            <?php View::fields( 'move_note', [ 'home_id' => $hh_thing['home_id'], 'kind' => 'item', 'note_id' => $hh_thing['id'] ] ); ?>
                            <?php foreach ( $hh_homes as $hh_home ) : ?>
                                <?php if ( $hh_home['id'] !== $hh_thing['home_id'] ) : ?>
                                    <button type="submit" class="quiet" name="target_home_id" value="<?php echo (int) $hh_home['id']; ?>">
                                        <?php
                                        /* translators: %s: the name of a home. */
                                        echo esc_html( sprintf( __( 'Move to %s', 'households' ), $hh_home['name'] ) );
                                        ?>
                                    </button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
<?php require __DIR__ . '/_foot.php'; ?>
