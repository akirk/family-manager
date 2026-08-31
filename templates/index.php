<?php
/**
 * The index is your day, not a directory of houses: where you are, what is
 * asked of you wherever it was written down, and the fortnight ahead across
 * every home you belong to.
 */

namespace Households;

$hh_user = View::user_id();
$hh_day = View::storage()->get_my_day( $hh_user );
$hh_where = $hh_day['where'];
$hh_homes = $hh_day['homes'];

require __DIR__ . '/_head.php';
?>
        <h1><?php echo esc_html__( 'Your day', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Where you are, what is yours to do, and what is coming across your homes.', 'households' ); ?></p>
        <?php View::notice(); ?>

        <section>
            <h2><?php echo esc_html__( 'Today', 'households' ); ?></h2>
            <?php if ( ! $hh_homes ) : ?>
                <?php /* Belonging to no home is the one thing this page cannot be about. */ ?>
                <p style="margin:0">
                    <?php echo esc_html__( 'You do not belong to a home yet.', 'households' ); ?>
                    <a href="<?php echo esc_url( View::base() . 'homes/' ); ?>"><?php echo esc_html__( 'Start one', 'households' ); ?></a>
                </p>
            <?php elseif ( $hh_where['known'] ) : ?>
                <p style="margin:0">
                    <?php
                    printf(
                        /* translators: %s: the name of a home. */
                        esc_html__( 'You are at %s today.', 'households' ),
                        '<a href="' . esc_url( View::home_url( $hh_where['home_id'] ) ) . '">' . esc_html( $hh_where['name'] ) . '</a>'
                    );
                    ?>
                    <?php if ( $hh_where['until'] && $hh_where['next_name'] ) : ?>
                        <span class="meta">
                            <?php
                            printf(
                                /* translators: 1: a date, 2: the name of a home. */
                                esc_html__( 'Until %1$s, then %2$s.', 'households' ),
                                esc_html( $hh_where['until_label'] ),
                                esc_html( $hh_where['next_name'] )
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                </p>
                <p class="meta" style="margin:4px 0 0">
                    <?php
                    echo $hh_where['with_you']
                        ? esc_html( sprintf(
                            /* translators: %s: a list of names. */
                            __( 'Here with you: %s.', 'households' ),
                            implode( ', ', $hh_where['with_you'] )
                        ) )
                        : esc_html__( 'Nobody else is here today.', 'households' );
                    ?>
                </p>
            <?php else : ?>
                <p style="margin:0"><strong><?php echo esc_html__( 'Where are you today?', 'households' ); ?></strong></p>
                <p class="meta" style="margin:4px 0 0"><?php echo esc_html__( 'Nothing says where you are, so nothing is assumed. Answering is about today alone; it sets up no arrangement.', 'households' ); ?></p>
            <?php endif; ?>

            <?php
            // Belong to one home and there is nothing to ask. Belong to more
            // and the answer is a button per home, the one you are at pressed.
            if ( count( $hh_homes ) > 1 ) :
                ?>
                <form method="post" class="actions" style="margin-top:10px">
                    <?php View::fields( 'say_where' ); ?>
                    <?php foreach ( $hh_homes as $hh_home ) : ?>
                        <button type="submit" name="said_home_id" value="<?php echo (int) $hh_home['id']; ?>"
                            class="<?php echo $hh_where['home_id'] === $hh_home['id'] ? 'primary' : ''; ?>"
                            aria-pressed="<?php echo $hh_where['home_id'] === $hh_home['id'] ? 'true' : 'false'; ?>">
                            <?php echo esc_html( $hh_home['name'] ); ?>
                        </button>
                    <?php endforeach; ?>
                    <?php if ( $hh_where['said'] ) : ?>
                        <button type="submit" name="said_home_id" value="0" class="quiet">
                            <?php echo $hh_where['rotates'] ? esc_html__( 'Back to the pattern', 'households' ) : esc_html__( 'Not sure', 'households' ); ?>
                        </button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>

        <?php if ( $hh_homes ) : ?>
            <section>
                <h2><?php echo esc_html__( 'Yours to do', 'households' ); ?></h2>
                <ul class="plain">
                    <?php if ( ! $hh_day['yours'] ) : ?>
                        <li class="empty"><?php echo esc_html__( 'Nothing is asked of you right now.', 'households' ); ?></li>
                    <?php endif; ?>
                    <?php foreach ( $hh_day['yours'] as $hh_task ) : ?>
                        <?php require __DIR__ . '/_task.php'; ?>
                    <?php endforeach; ?>
                </ul>
            </section>

            <?php if ( $hh_day['shared'] ) : ?>
                <section>
                    <h2><?php echo esc_html__( 'Nobody’s yet', 'households' ); ?></h2>
                    <p class="meta"><?php echo esc_html__( 'Written down for the house rather than for a person. Ticking one is claiming it.', 'households' ); ?></p>
                    <ul class="plain">
                        <?php foreach ( $hh_day['shared'] as $hh_task ) : ?>
                            <?php require __DIR__ . '/_task.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section>
                <h2><?php echo esc_html__( 'The fortnight ahead', 'households' ); ?></h2>
                <ul class="plain">
                    <?php if ( ! $hh_day['agenda'] ) : ?>
                        <li class="empty"><?php echo esc_html__( 'Nothing due, nobody moving, no birthdays in the next fortnight.', 'households' ); ?></li>
                    <?php endif; ?>
                    <?php foreach ( $hh_day['agenda'] as $hh_entry ) : ?>
                        <?php
                        $hh_line = $hh_entry['title'];
                        $hh_meta = '';
                        if ( 'birthday' === $hh_entry['kind'] ) {
                            /* translators: 1: a name, 2: an age. */
                            $hh_line = sprintf( __( '%1$s turns %2$d', 'households' ), $hh_entry['title'], $hh_entry['turning'] );
                        } elseif ( 'move' === $hh_entry['kind'] ) {
                            /* translators: 1: a list of names, 2: the home they leave, 3: the home they arrive at. */
                            $hh_line = sprintf(
                                __( '%1$s: %2$s to %3$s', 'households' ),
                                implode( ', ', $hh_entry['people'] ),
                                $hh_entry['from_name'],
                                $hh_entry['home_name']
                            );
                        } else {
                            /* translators: %s: a name. */
                            $hh_meta = $hh_entry['who'] ? sprintf( __( 'for %s', 'households' ), $hh_entry['who'] ) : __( 'for the house', 'households' );
                            if ( 'appointment' === $hh_entry['kind'] ) {
                                $hh_meta .= ' · ' . __( 'appointment', 'households' );
                            }
                        }
                        ?>
                        <li class="row">
                            <div class="grow">
                                <strong><?php echo esc_html( $hh_line ); ?></strong>
                                <?php if ( $hh_meta ) : ?>
                                    <div class="meta"><?php echo esc_html( $hh_meta ); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="actions">
                                <?php // A move names both homes in its line; the pill would say it twice. ?>
                                <?php if ( $hh_entry['home_id'] && 'move' !== $hh_entry['kind'] ) : ?>
                                    <a class="pill" style="text-decoration:none" href="<?php echo esc_url( View::home_url( $hh_entry['home_id'] ) ); ?>">
                                        <?php
                                        /* translators: %s: the name of a home. */
                                        echo esc_html( sprintf( __( 'at %s', 'households' ), $hh_entry['home_name'] ) );
                                        ?>
                                    </a>
                                <?php endif; ?>
                                <span class="pill"><?php echo esc_html( $hh_entry['when'] ); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <p class="subtitle">
            <a href="<?php echo esc_url( View::base() . 'homes/' ); ?>"><?php echo esc_html__( 'Your homes, and starting another', 'households' ); ?></a>
            ·
            <a href="<?php echo esc_url( View::base() . 'where/' ); ?>"><?php echo esc_html__( 'Who is where, day by day', 'households' ); ?></a>
            ·
            <a href="<?php echo esc_url( View::base() . 'things/' ); ?>"><?php echo esc_html__( 'Everything kept across your homes', 'households' ); ?></a>
        </p>
<?php require __DIR__ . '/_foot.php'; ?>
