<?php
/**
 * Every home you belong to, and where a new one is started. A home with
 * nobody in it is not a home, so starting one puts you in it.
 */

namespace Households;

$hh_user = View::user_id();
$hh_homes = View::storage()->get_homes_overview( $hh_user );

require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Your day', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Your homes', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Every home you belong to, and who is under each roof today.', 'households' ); ?></p>
        <?php View::notice(); ?>

        <section>
            <ul class="plain">
                <?php if ( ! $hh_homes ) : ?>
                    <li class="empty"><?php echo esc_html__( 'You do not belong to a home yet. Start one below and it is yours.', 'households' ); ?></li>
                <?php endif; ?>
                <?php foreach ( $hh_homes as $hh_home ) : ?>
                    <li class="row">
                        <?php // Wide enough that the buttons drop to their own line on a phone. ?>
                        <div class="grow">
                            <h2 style="margin:0 0 2px;font-size:1.15rem">
                                <a style="text-decoration:none" href="<?php echo esc_url( View::home_url( $hh_home['id'] ) ); ?>"><?php echo esc_html( $hh_home['name'] ); ?></a>
                            </h2>
                            <div class="meta">
                                <?php
                                $hh_here = wp_list_pluck( $hh_home['here'], 'name' );
                                echo $hh_here
                                    ? esc_html__( 'Here today:', 'households' ) . ' ' . esc_html( implode( ', ', $hh_here ) )
                                    : esc_html__( 'Nobody here today.', 'households' );
                                ?>
                            </div>
                            <div class="meta">
                                <?php if ( ! $hh_home['open_tasks'] ) : ?>
                                    <?php echo esc_html__( 'Nothing to do', 'households' ); ?>
                                <?php else : ?>
                                    <?php
                                    printf(
                                        esc_html(
                                            /* translators: %d: how many things are still to do. */
                                            _n( '%d thing to do', '%d things to do', $hh_home['open_tasks'], 'households' )
                                        ),
                                        (int) $hh_home['open_tasks']
                                    );
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="actions">
                            <a class="button primary" href="<?php echo esc_url( View::home_url( $hh_home['id'] ) ); ?>"><?php echo esc_html__( 'Open', 'households' ); ?></a>
                            <?php if ( $hh_home['can_manage'] ) : ?>
                                <a class="button" href="<?php echo esc_url( View::home_url( $hh_home['id'], 'manage/' ) ); ?>"><?php echo esc_html__( 'Manage', 'households' ); ?></a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Start a home', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'A house, a flat, a grandparent’s spare room — anywhere the family keeps things and people. You will be the first person in it and the one who administers it; everyone else is added from the home itself, which is where this takes you.', 'households' ); ?></p>
            <form method="post" class="grid">
                <?php View::fields( 'start_home' ); ?>
                <label class="wide"><?php echo esc_html__( 'What is it called?', 'households' ); ?>
                    <input type="text" name="name" required maxlength="80" placeholder="<?php echo esc_attr__( 'Home', 'households' ); ?>">
                </label>
                <div><button class="primary" type="submit"><?php echo esc_html__( 'Start it', 'households' ); ?></button></div>
            </form>
        </section>
<?php require __DIR__ . '/_foot.php'; ?>
