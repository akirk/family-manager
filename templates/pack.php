<?php
/**
 * What is waiting to be taken from one household to another.
 *
 * Saying a thing is to go somewhere does not put it there — it is on its shelf
 * until somebody carries it — so what has been said that way is gathered here,
 * a trip at a time. Things going the same way are one list, because that is one
 * bag, and the list says when the trip is when the fortnight already holds one.
 * Ticking something off is saying it has got there: from the moment it is in
 * the bag, the household it was going to is where to look for it.
 */

namespace Households;

$hh_user = View::user_id();
$hh_routes = View::storage()->get_packlist( $hh_user );
$hh_homes = View::storage()->get_homes_for_user( $hh_user );

$hh_title = __( 'What to pack', 'households' );

require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Overview', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'What to pack', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'What is waiting to be taken from one household to another, and the trip it is waiting for.', 'households' ); ?></p>
        <?php View::notice(); ?>

        <?php if ( ! $hh_routes ) : ?>
            <section>
                <ul class="plain">
                    <li class="empty">
                        <?php echo esc_html__( 'Nothing is waiting to go anywhere.', 'households' ); ?>
                        <?php // Where the lists are made: a thing is put on one from the household it is at. ?>
                        <a href="<?php echo esc_url( View::base() . 'things/' ); ?>"><?php echo esc_html__( 'Say a thing is to go somewhere', 'households' ); ?></a>
                        <?php echo esc_html__( 'and it waits here until it is packed.', 'households' ); ?>
                    </li>
                </ul>
            </section>
        <?php endif; ?>

        <?php foreach ( $hh_routes as $hh_route ) : ?>
            <section id="going-<?php echo (int) $hh_route['from_id']; ?>-<?php echo (int) $hh_route['to_id']; ?>">
                <div class="row heading">
                    <h2>
                        <?php if ( $hh_route['from_name'] ) : ?>
                            <?php
                            printf(
                                /* translators: 1: the household being left, linked, 2: the household being arrived at, linked. */
                                esc_html__( '%1$s to %2$s', 'households' ),
                                '<a href="' . esc_url( View::home_url( $hh_route['from_id'] ) ) . '">' . esc_html( $hh_route['from_name'] ) . '</a>',
                                '<a href="' . esc_url( View::home_url( $hh_route['to_id'] ) ) . '">' . esc_html( $hh_route['to_name'] ) . '</a>'
                            );
                            ?>
                        <?php else : ?>
                            <?php // Nothing this page may say names the house they are coming from, so the list has one end rather than a guess or a household of somebody else's at the other. ?>
                            <?php
                            printf(
                                /* translators: %s: the household being arrived at, linked. */
                                esc_html__( 'To go to %s', 'households' ),
                                '<a href="' . esc_url( View::home_url( $hh_route['to_id'] ) ) . '">' . esc_html( $hh_route['to_name'] ) . '</a>'
                            );
                            ?>
                        <?php endif; ?>
                    </h2>
                    <?php // The trip the bag is for, if the fortnight holds one. It is a day on the board, so the pill is the way to it. ?>
                    <?php if ( $hh_route['when'] ) : ?>
                        <div class="actions">
                            <a class="pill" href="<?php echo esc_url( add_query_arg( 'home', $hh_route['to_id'], View::base() . 'where/' ) ); ?>">
                                <?php echo esc_html( $hh_route['when'] ); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( $hh_route['people'] ) : ?>
                    <?php // Whose trip it is. Which day it is is the pill above, so it is not said twice. ?>
                    <p class="meta" style="margin:-4px 0 10px"><?php echo esc_html( implode( ', ', $hh_route['people'] ) ); ?></p>
                <?php elseif ( $hh_route['from_name'] ) : ?>
                    <p class="meta" style="margin:-4px 0 10px">
                        <?php echo esc_html__( 'Nobody is going that way in the next fortnight. It waits until somebody does.', 'households' ); ?>
                    </p>
                <?php elseif ( $hh_route['from_id'] ) : ?>
                    <p class="meta" style="margin:-4px 0 10px">
                        <?php echo esc_html__( 'They are at a household that is not yours, so there is no trip here to read. They come back by somebody saying they have.', 'households' ); ?>
                    </p>
                <?php else : ?>
                    <p class="meta" style="margin:-4px 0 10px">
                        <?php echo esc_html__( 'Nobody has said which household these are at, so there is no trip to read.', 'households' ); ?>
                    </p>
                <?php endif; ?>

                <ul class="plain">
                    <?php foreach ( $hh_route['things'] as $hh_thing ) : ?>
                        <?php
                        // The heading is the trip, so it has named both ends of
                        // it and the line says only what the thing is and, from
                        // the house it is leaving, where in it to find it.
                        $hh_thing_home = $hh_route['from_name'] ? $hh_route['from_id'] : 0;
                        $hh_thing_writing = true;
                        $hh_thing_going_said = true;
                        ?>
                        <?php require __DIR__ . '/_thing.php'; ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
<?php require __DIR__ . '/_foot.php'; ?>
