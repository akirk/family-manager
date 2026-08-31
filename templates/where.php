<?php
/**
 * Everyone across the households you belong to, where they are today, and
 * the fortnight ahead. It spans households, so it says which one it is
 * reading from.
 */

namespace Households;

$hh_user = View::user_id();
$hh_homes = View::storage()->get_homes_for_user( $hh_user );

// The home this is read from: the one asked for, if it is one of yours, else
// the last one you looked at.
$hh_asked = isset( $_GET['home'] ) ? absint( wp_unslash( $_GET['home'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$hh_home_id = $hh_asked && Access::can_reach( $hh_user, $hh_asked ) ? $hh_asked : View::storage()->last_home_id( $hh_user );

$hh_board = $hh_home_id ? View::storage()->get_whereabouts_board( $hh_home_id ) : [];
$hh_everyone = View::storage()->get_people_overview( $hh_user );
$hh_organises = $hh_home_id && current_user_can( 'organise_household', $hh_home_id );
$hh_today = current_time( 'Y-m-d' );

$hh_title = __( 'Who is where', 'households' );

require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Your day', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Who is where', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Everyone across the households you belong to, and where they are today.', 'households' ); ?></p>
        <?php View::notice(); ?>

        <?php if ( ! $hh_board ) : ?>
            <section><p class="empty"><?php echo esc_html__( 'You do not have a household yet.', 'households' ); ?></p></section>
            <?php require __DIR__ . '/_foot.php'; ?>
            <?php return; ?>
        <?php endif; ?>

        <section>
            <h2><?php echo esc_html__( 'Everyone', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'Move someone to another household to say where they are today. It is about today alone and leaves the pattern alone; somewhere they have never been, they join by being sent there.', 'households' ); ?></p>
            <ul class="plain">
                <?php foreach ( $hh_everyone as $hh_one ) : ?>
                    <li class="row">
                        <div class="grow">
                            <a style="font-weight:700" href="<?php echo esc_url( View::person_url( $hh_one['id'] ) ); ?>"><?php echo esc_html( $hh_one['name'] ); ?></a>
                            <div class="meta"><?php echo esc_html( implode( ' · ', wp_list_pluck( $hh_one['homes'], 'name' ) ) ); ?></div>
                            <div class="actions" style="margin-top:4px">
                                <?php if ( $hh_one['is_you'] ) : ?>
                                    <span class="pill"><?php echo esc_html__( 'You', 'households' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $hh_one['is_child'] ) : ?>
                                    <span class="pill"><?php echo esc_html__( 'Child', 'households' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $hh_one['label'] ) : ?>
                                    <span class="pill"><?php echo esc_html( $hh_one['label'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! $hh_one['user_id'] ) : ?>
                                    <span class="pill warm"><?php echo esc_html__( 'No account', 'households' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Saying where you are is something anyone may do
                            // about themselves. Saying it about someone else is
                            // organising, so it goes through a household you
                            // organise and they belong to — the first one that
                            // is both, since which one it is asked through
                            // changes nothing about the answer.
                            $hh_via = 0;
                            foreach ( $hh_one['homes'] as $hh_their ) {
                                if ( current_user_can( 'organise_household', $hh_their['id'] ) ) {
                                    $hh_via = (int) $hh_their['id'];
                                    break;
                                }
                            }
                            $hh_may_move = $hh_one['is_you'] || $hh_via;

                            // Where they could be: their own households, and
                            // any of yours you organise. Somewhere they have
                            // never been is still somewhere they can be sent —
                            // the first weekend at the grandparents is a move
                            // before it is an arrangement — and being sent puts
                            // them in it.
                            $hh_targets = $hh_one['homes'];
                            $hh_ids = wp_list_pluck( $hh_targets, 'id' );
                            foreach ( $hh_homes as $hh_mine ) {
                                if ( ! in_array( $hh_mine['id'], $hh_ids, true ) && current_user_can( 'organise_household', $hh_mine['id'] ) ) {
                                    $hh_targets[] = $hh_mine;
                                }
                            }
                            usort( $hh_targets, static function( array $a, array $b ): int {
                                return strcasecmp( $a['name'], $b['name'] );
                            } );
                            ?>
                            <?php if ( $hh_may_move && ( count( $hh_targets ) > 1 || $hh_one['said'] ) ) : ?>
                                <form method="post" class="actions" style="margin-top:6px">
                                    <?php
                                    View::fields(
                                        $hh_one['is_you'] ? 'say_where' : 'set_override',
                                        $hh_one['is_you']
                                            ? [ 'date' => $hh_today ]
                                            : [ 'home_id' => $hh_via, 'person_id' => $hh_one['id'], 'date' => $hh_today ]
                                    );
                                    $hh_field = $hh_one['is_you'] ? 'said_home_id' : 'override_home_id';
                                    ?>
                                    <?php foreach ( $hh_targets as $hh_their ) : ?>
                                        <?php $hh_at_it = $hh_one['location']['home_id'] === $hh_their['id']; ?>
                                        <button type="submit" name="<?php echo esc_attr( $hh_field ); ?>" value="<?php echo (int) $hh_their['id']; ?>"
                                            class="<?php echo $hh_at_it ? 'primary' : ''; ?>"
                                            aria-pressed="<?php echo $hh_at_it ? 'true' : 'false'; ?>">
                                            <?php echo esc_html( $hh_their['name'] ); ?>
                                        </button>
                                    <?php endforeach; ?>
                                    <?php if ( $hh_one['said'] ) : ?>
                                        <button type="submit" name="<?php echo esc_attr( $hh_field ); ?>" value="0" class="quiet">
                                            <?php echo $hh_one['rotates'] ? esc_html__( 'Back to the pattern', 'households' ) : esc_html__( 'Elsewhere', 'households' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php // Somewhere known, or honestly nowhere: guessing would read as an answer. ?>
                        <?php if ( $hh_one['location']['known'] ) : ?>
                            <span class="pill">
                                <?php
                                /* translators: %s: the name of a household. */
                                echo esc_html( sprintf( __( 'at %s', 'households' ), $hh_one['location']['name'] ) );
                                ?>
                            </span>
                        <?php else : ?>
                            <span class="pill warm"><?php echo esc_html__( 'not tracked', 'households' ); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section>
            <h2><?php echo esc_html__( 'The next fortnight', 'households' ); ?></h2>
            <p class="meta">
                <?php
                /* translators: %s: the name of a household. */
                echo esc_html( sprintf( __( 'Read from %s.', 'households' ), $hh_board['home']['name'] ) );
                ?>
            </p>
            <?php if ( count( $hh_homes ) > 1 ) : ?>
                <p class="meta">
                    <?php echo esc_html__( 'Look at another household', 'households' ); ?>
                    <?php foreach ( $hh_homes as $hh_home ) : ?>
                        <?php if ( $hh_home['id'] !== $hh_board['home']['id'] ) : ?>
                            <a style="margin-right:8px" href="<?php echo esc_url( add_query_arg( 'home', $hh_home['id'], View::base() . 'where/' ) ); ?>"><?php echo esc_html( $hh_home['name'] ); ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <?php if ( $hh_organises ) : ?>
                <p class="meta"><?php echo esc_html__( 'Tap a day to move it. Moving one day leaves the pattern alone.', 'households' ); ?></p>
            <?php endif; ?>

            <div style="overflow-x:auto">
                <table style="border-collapse:collapse;min-width:100%">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:4px 8px"></th>
                            <?php foreach ( $hh_board['dates'] as $hh_date ) : ?>
                                <th style="padding:4px 2px;font-size:0.72rem;font-weight:700;<?php
                                    echo $hh_date['is_weekend'] ? 'color:var(--hh-warm);' : '';
                                    echo $hh_date['is_today'] ? 'text-decoration:underline;' : '';
                                ?>">
                                    <?php echo esc_html( $hh_date['weekday'] ); ?><br><?php echo esc_html( $hh_date['day'] ); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $hh_board['people'] as $hh_one ) : ?>
                            <?php $hh_days = array_column( $hh_one['days'], null, 'date' ); ?>
                            <tr>
                                <th style="text-align:left;padding:4px 8px;font-weight:700;white-space:nowrap">
                                    <a style="text-decoration:none" href="<?php echo esc_url( View::person_url( $hh_one['id'] ) ); ?>"><?php echo esc_html( $hh_one['name'] ); ?></a>
                                </th>
                                <?php foreach ( $hh_board['dates'] as $hh_date ) : ?>
                                    <?php
                                    $hh_day = isset( $hh_days[ $hh_date['date'] ] ) ? $hh_days[ $hh_date['date'] ] : null;
                                    $hh_letter = $hh_day && $hh_day['home_name'] ? mb_substr( $hh_day['home_name'], 0, 1 ) : '·';
                                    // Cycle through this person's homes, then back
                                    // to the pattern, which is what a home ID of 0
                                    // means. A day nothing accounts for starts at
                                    // the first home.
                                    $hh_options = array_merge( wp_list_pluck( $hh_one['homes'], 'id' ), [ 0 ] );
                                    $hh_at = $hh_day && $hh_day['is_override'] ? array_search( $hh_day['home_id'], $hh_options, true ) : count( $hh_options ) - 1;
                                    $hh_next = $hh_options[ ( (int) $hh_at + 1 ) % count( $hh_options ) ];
                                    ?>
                                    <td title="<?php echo esc_attr( $hh_day ? $hh_day['home_name'] : '' ); ?>"
                                        style="padding:0;text-align:center;border:1px solid var(--hh-line);<?php
                                            echo $hh_day && $hh_day['is_here'] ? 'background:color-mix(in srgb, var(--hh-accent) 22%, transparent);' : '';
                                            echo $hh_day && $hh_day['is_override'] ? 'outline:2px solid var(--hh-warm);outline-offset:-2px;' : '';
                                        ?>">
                                        <?php if ( $hh_day && $hh_organises ) : ?>
                                            <form method="post" style="margin:0">
                                                <?php
                                                View::fields( 'set_override', [
                                                    'home_id'          => $hh_board['home']['id'],
                                                    'person_id'        => $hh_one['id'],
                                                    'date'             => $hh_day['date'],
                                                    'override_home_id' => $hh_next,
                                                ] );
                                                ?>
                                                <button type="submit" style="width:100%;min-height:30px;padding:0;border:0;border-radius:0;background:transparent;color:inherit">
                                                    <?php echo esc_html( $hh_letter ); ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span style="display:inline-block;min-height:30px;line-height:30px"><?php echo esc_html( $hh_letter ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Handovers', 'households' ); ?></h2>
            <ul class="plain">
                <?php if ( ! $hh_board['handovers'] ) : ?>
                    <li class="empty"><?php echo esc_html__( 'No handovers in this window.', 'households' ); ?></li>
                <?php endif; ?>
                <?php foreach ( $hh_board['handovers'] as $hh_handover ) : ?>
                    <li class="row">
                        <div class="grow">
                            <strong><?php echo esc_html( View::date( $hh_handover['date'] ) ); ?></strong>
                            <div class="meta">
                                <?php echo esc_html( implode( ', ', $hh_handover['people'] ) ); ?> ·
                                <?php echo esc_html( $hh_handover['from_name'] ); ?> &rarr; <?php echo esc_html( $hh_handover['to_name'] ); ?>
                            </div>
                        </div>
                        <span class="pill">
                            <?php
                            if ( 'out' === $hh_handover['direction'] ) {
                                echo esc_html__( 'leaving', 'households' );
                            } elseif ( 'in' === $hh_handover['direction'] ) {
                                echo esc_html__( 'arriving', 'households' );
                            } else {
                                echo esc_html__( 'elsewhere', 'households' );
                            }
                            ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php
        $hh_rotating = array_values( array_filter( $hh_board['people'], static function ( array $person ): bool {
            return $person['can_rotate'];
        } ) );
        ?>
        <?php if ( $hh_organises && $hh_rotating ) : ?>
            <section>
                <h2><?php echo esc_html__( 'Rotations', 'households' ); ?></h2>
                <p class="meta"><?php echo esc_html__( 'A rotation names its households in order and repeats a cycle of days. It is stored on the person, so every household reads the same answer.', 'households' ); ?></p>
                <?php foreach ( $hh_rotating as $hh_one ) : ?>
                    <div style="border-top:1px solid var(--hh-line);padding-top:12px;margin-top:12px">
                        <form method="post" class="grid">
                            <?php View::fields( 'save_rotation', [ 'home_id' => $hh_board['home']['id'], 'person_id' => $hh_one['id'] ] ); ?>
                            <div class="wide"><strong><?php echo esc_html( $hh_one['name'] ); ?></strong></div>
                            <label><?php echo esc_html__( 'Pattern', 'households' ); ?>
                                <select name="pattern">
                                    <?php foreach ( $hh_board['patterns'] as $hh_pattern ) : ?>
                                        <option value="<?php echo esc_attr( $hh_pattern['key'] ); ?>" <?php selected( $hh_one['rotation']['pattern'] ?? '', $hh_pattern['key'] ); ?>>
                                            <?php echo esc_html( $hh_pattern['label'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label><?php echo esc_html__( 'Starts', 'households' ); ?>
                                <input type="date" name="start_date" value="<?php echo esc_attr( $hh_one['rotation']['start_date'] ?? '' ); ?>">
                            </label>
                            <label><?php echo esc_html__( 'Changeover', 'households' ); ?>
                                <input type="time" name="changeover_time" value="<?php echo esc_attr( $hh_one['rotation']['changeover_time'] ?? Whereabouts::DEFAULT_CHANGEOVER_TIME ); ?>">
                            </label>
                            <div class="wide">
                                <div class="meta"><?php echo esc_html__( 'Households, in order', 'households' ); ?></div>
                                <div class="actions">
                                    <?php foreach ( $hh_one['homes'] as $hh_home ) : ?>
                                        <label class="inline">
                                            <input type="checkbox" name="homes[]" value="<?php echo (int) $hh_home['id']; ?>"
                                                <?php checked( in_array( $hh_home['id'], $hh_one['rotation']['homes'] ?? [], true ) ); ?>>
                                            <span><?php echo esc_html( $hh_home['name'] ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button class="primary" type="submit"><?php echo esc_html__( 'Save rotation', 'households' ); ?></button>
                        </form>
                        <?php if ( $hh_one['rotation'] ) : ?>
                            <form method="post" style="margin-top:10px">
                                <?php View::fields( 'clear_rotation', [ 'home_id' => $hh_board['home']['id'], 'person_id' => $hh_one['id'] ] ); ?>
                                <button type="submit" class="quiet"><?php echo esc_html__( 'Clear', 'households' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
