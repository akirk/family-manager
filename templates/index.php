<?php
/**
 * The index is your day, not a directory of houses. It reads in two: what the
 * household you are standing in asks of you and what is kept there on one side;
 * where everyone is, and what the fortnight holds, on the other.
 */

namespace Households;

$hh_user = View::user_id();
$hh_me = View::person_id();
$hh_day = View::storage()->get_my_day( $hh_user );
$hh_where = $hh_day['where'];
$hh_homes = $hh_day['homes'];
// The household you are standing in, as its own page reads it. Nothing says
// where you are, and there is no household to read: the sections say so.
$hh_here = $hh_day['here'];
// Whoever you may say a day for: yourself, and anyone in a household you
// organise. Nobody to name, and a household is simply somewhere you go.
$hh_party = $hh_me ? $hh_day['party'] : [];

// What is still open where you are, everybody's by default. Whose it is is a
// filter rather than a heading, and it is in the URL so it survives a tick and
// can be passed to somebody else; so is the form for adding one.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$hh_mine = ! empty( $_GET['mine'] );
$hh_adding = ! empty( $_GET['add'] );
// Everything ever ticked off here, rather than the last week of it.
$hh_earlier = ! empty( $_GET['earlier'] );
// Which row is open to be written, if any.
$hh_editing = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$hh_url = remove_query_arg( 'problem' );

// Whose the list is is the viewer's question and asked here; what is still
// worth seeing of what is done is the list's own, and asked the same way
// wherever a list is read.
$hh_ours = [];
foreach ( isset( $hh_here['tasks'] ) ? $hh_here['tasks'] : [] as $hh_task ) {
    if ( ! $hh_mine || $hh_task['person_id'] === $hh_me ) {
        $hh_ours[] = $hh_task;
    }
}
$hh_sifted = Storage::sift_tasks( $hh_ours, $hh_earlier );
$hh_tasks = $hh_sifted['tasks'];
$hh_quiet = $hh_sifted['quiet'];
$hh_can_add = ! empty( $hh_here['viewer']['can_organise'] );
$hh_open = add_query_arg( 'add', 1, $hh_url );
$hh_close = remove_query_arg( 'add', $hh_url );

$hh_title = __( 'Overview', 'households' );

require __DIR__ . '/_head.php';
?>
        <h1><?php echo esc_html__( 'Overview', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Where you are, what is yours to do, and what is coming across your households.', 'households' ); ?></p>
        <?php View::notice(); ?>

        <?php if ( ! $hh_homes ) : ?>
            <?php /* Having no household at all is the one thing this page cannot be about. */ ?>
            <section>
                <p style="margin:0">
                    <?php echo esc_html__( 'You do not have a household yet.', 'households' ); ?>
                    <a href="<?php echo esc_url( View::base() . 'homes/' ); ?>"><?php echo esc_html__( 'Add one', 'households' ); ?></a>
                </p>
            </section>
        <?php else : ?>

        <div class="columns">
            <div>
                <section id="hh-todo" data-hh-live-section>
                    <div class="row heading">
                        <h2>
                            <?php
                            echo $hh_where['known']
                                ? esc_html( sprintf(
                                    /* translators: %s: the name of a household. */
                                    __( 'To do in %s', 'households' ),
                                    $hh_where['name']
                                ) )
                                : esc_html__( 'To do', 'households' );
                            ?>
                        </h2>
                        <?php if ( $hh_where['known'] ) : ?>
                            <div class="actions">
                                <?php // Everybody's by default, because a household's list is not a private one. The pill says the list it would give you, not the one you are reading. ?>
                                <a class="pill" data-hh-live
                                    href="<?php echo esc_url( $hh_mine ? remove_query_arg( 'mine', $hh_url ) : add_query_arg( 'mine', 1, $hh_url ) ); ?>">
                                    <?php echo $hh_mine ? esc_html__( 'everyone', 'households' ) : esc_html__( 'just me', 'households' ); ?>
                                </a>
                                <?php // A filter over a list you are reading, so it waits for there to be one: an empty list says where the rest went in words instead. ?>
                                <?php if ( ( $hh_tasks && $hh_quiet ) || $hh_earlier ) : ?>
                                    <a class="pill" data-hh-live
                                        href="<?php echo esc_url( $hh_earlier ? remove_query_arg( 'earlier', $hh_url ) : add_query_arg( 'earlier', 1, $hh_url ) ); ?>">
                                        <?php echo $hh_earlier ? esc_html__( 'the past week', 'households' ) : esc_html__( 'done earlier', 'households' ); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ( $hh_can_add ) : ?>
                                    <?php // A link that asks the page for the form; the script opens the one already here instead. ?>
                                    <a class="pill" data-hh-add href="<?php echo esc_url( $hh_adding ? $hh_close : $hh_open ); ?>"
                                        data-hh-open="<?php echo esc_url( $hh_open ); ?>" data-hh-close="<?php echo esc_url( $hh_close ); ?>"
                                        aria-controls="add" aria-expanded="<?php echo $hh_adding ? 'true' : 'false'; ?>"
                                        aria-label="<?php echo esc_attr__( 'Write something down', 'households' ); ?>"><?php echo $hh_adding ? '&times;' : '+'; ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ( $hh_can_add ) : ?>
                        <form method="post" class="grid" id="add" style="margin-bottom:12px" <?php echo $hh_adding ? '' : 'hidden'; ?>>
                            <?php View::fields( 'add_task', [ 'home_id' => $hh_where['home_id'] ] ); ?>
                            <label class="wide"><?php echo esc_html__( 'What needs doing', 'households' ); ?>
                                <input type="text" name="title" required <?php echo $hh_adding ? 'autofocus' : ''; ?>>
                            </label>
                            <label><?php echo esc_html__( 'For whom', 'households' ); ?>
                                <select name="person_id">
                                    <option value="0"><?php echo esc_html__( 'Everyone', 'households' ); ?></option>
                                    <?php foreach ( $hh_here['people'] as $hh_person ) : ?>
                                        <option value="<?php echo (int) $hh_person['id']; ?>"><?php echo esc_html( $hh_person['name'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label><?php echo esc_html__( 'Kind', 'households' ); ?>
                                <select name="task_type">
                                    <option value="task"><?php echo esc_html__( 'Task', 'households' ); ?></option>
                                    <option value="appointment"><?php echo esc_html__( 'Appointment', 'households' ); ?></option>
                                </select>
                            </label>
                            <label><?php echo esc_html__( 'When', 'households' ); ?>
                                <input type="date" name="due_date">
                            </label>
                            <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                        </form>
                    <?php endif; ?>

                    <ul class="plain">
                        <?php if ( ! $hh_where['known'] ) : ?>
                            <li class="empty"><?php echo esc_html__( 'Nothing says where you are today, so there is no list to read.', 'households' ); ?></li>
                        <?php elseif ( ! $hh_tasks ) : ?>
                            <li class="empty">
                                <?php
                                echo $hh_mine
                                    ? esc_html__( 'Nothing here is asked of you.', 'households' )
                                    : esc_html__( 'Nothing to do here.', 'households' );
                                ?>
                                <?php // An empty list with something behind it says where the rest went; the pill above it is the same door, and this is the one place you would look for it. ?>
                                <?php if ( $hh_quiet && ! $hh_earlier ) : ?>
                                    <a data-hh-live href="<?php echo esc_url( add_query_arg( 'earlier', 1, $hh_url ) ); ?>"><?php echo esc_html__( 'See what was done earlier.', 'households' ); ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        <?php
                        $hh_task_home = $hh_where['home_id'];
                        $hh_task_url = $hh_url;
                        $hh_task_people = isset( $hh_here['people'] ) ? $hh_here['people'] : [];
                        $hh_task_writing = $hh_can_add;
                        $hh_task_editing = $hh_editing;
                        ?>
                        <?php foreach ( $hh_tasks as $hh_task ) : ?>
                            <?php require __DIR__ . '/_task.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <?php // A thing is at one household at a time, so this is the shelf you can actually reach today. ?>
                <section>
                    <div class="row heading">
                        <h2>
                            <?php
                            echo $hh_where['known']
                                ? esc_html( sprintf(
                                    /* translators: %s: the name of a household. */
                                    __( 'Things kept at %s', 'households' ),
                                    $hh_where['name']
                                ) )
                                : esc_html__( 'Things kept where you are', 'households' );
                            ?>
                        </h2>
                        <div class="actions">
                            <a class="pill" href="<?php echo esc_url( View::base() . 'things/' ); ?>"
                                aria-label="<?php echo esc_attr__( 'Everything kept across your households', 'households' ); ?>">
                                <?php echo esc_html__( 'all', 'households' ); ?>
                            </a>
                        </div>
                    </div>
                    <ul class="plain">
                        <?php if ( ! $hh_where['known'] ) : ?>
                            <li class="empty"><?php echo esc_html__( 'Nothing says where you are today, so nothing can be said about what is within reach.', 'households' ); ?></li>
                        <?php elseif ( ! $hh_here['items'] ) : ?>
                            <li class="empty"><?php echo esc_html__( 'Nothing listed here yet.', 'households' ); ?></li>
                        <?php endif; ?>
                        <?php foreach ( isset( $hh_here['items'] ) ? $hh_here['items'] : [] as $hh_thing ) : ?>
                            <?php
                            // The shelf you can actually reach, so a thing that
                            // lives here but has gone somewhere else says so:
                            // the drawer it lives in is no help today.
                            $hh_thing_at = ! empty( $hh_thing['at'] ) ? $hh_thing['at'] : [];
                            $hh_thing_away = ! empty( $hh_thing_at['home_id'] ) && $hh_thing_at['home_id'] !== $hh_here['home']['id'];
                            ?>
                            <li class="row">
                                <div class="grow">
                                    <strong><a href="<?php echo esc_url( View::thing_url( $hh_thing['id'] ) ); ?>"><?php echo esc_html( $hh_thing['title'] ); ?></a></strong>
                                    <?php if ( $hh_thing['detail'] ) : ?>
                                        <div class="meta"><?php echo esc_html( $hh_thing['detail'] ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( $hh_thing_away && Access::can_reach( $hh_user, $hh_thing_at['home_id'] ) ) : ?>
                                        <div class="meta">
                                            <?php
                                            /* translators: %s: the name of a household. */
                                            echo esc_html( sprintf( __( 'It is at %s just now.', 'households' ), $hh_thing_at['name'] ) );
                                            ?>
                                        </div>
                                    <?php elseif ( $hh_thing_away ) : ?>
                                        <div class="meta"><?php echo esc_html__( 'It is not at any of your households just now.', 'households' ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>

            <div>
                <section>
                    <?php // The headings are the way on: each is the name of the page it opens. ?>
                    <h2><a href="<?php echo esc_url( View::base() . 'homes/' ); ?>"><?php echo esc_html__( 'Your households', 'households' ); ?></a></h2>
                    <?php // Said only when there is something to say: where you are is shown by which line is filled in. ?>
                    <?php if ( ! $hh_me ) : ?>
                        <?php // Administering a household is not living in it, and this page is about your day, not theirs. ?>
                        <p class="meta"><?php echo esc_html__( 'You are not in any of these households yourself, so there is nothing here about your day. Add yourself to one and this page fills in.', 'households' ); ?></p>
                    <?php elseif ( ! $hh_where['known'] ) : ?>
                        <p class="meta"><?php echo esc_html__( 'Nothing says where you are today. Open one and say — it counts for today alone.', 'households' ); ?></p>
                    <?php endif; ?>
                    <ul class="plain homes">
                        <?php foreach ( $hh_homes as $hh_home ) : ?>
                            <?php $hh_at = $hh_home['id'] === $hh_where['home_id']; ?>
                            <li>
                                <?php // The line is the arrow: opening it is asking who is going, and the name in it is still the way in. ?>
                                <details class="home<?php echo $hh_at ? ' at' : ''; ?>">
                                    <summary>
                                        <a style="text-decoration:none" href="<?php echo esc_url( View::home_url( $hh_home['id'] ) ); ?>"><?php echo esc_html( $hh_home['name'] ); ?></a>
                                        <span class="who meta"><?php echo esc_html( implode( ', ', wp_list_pluck( $hh_home['here'], 'name' ) ) ); ?></span>
                                    </summary>
                                    <?php if ( $hh_party ) : ?>
                                        <form method="post">
                                            <?php View::fields( 'say_where', [ 'said_home_id' => $hh_home['id'] ] ); ?>
                                            <div class="going">
                                                <?php foreach ( $hh_party as $hh_person ) : ?>
                                                    <label class="inline">
                                                        <input type="checkbox" name="people[]" value="<?php echo (int) $hh_person['id']; ?>"
                                                            <?php checked( $hh_person['is_you'] && ! $hh_at ); ?>>
                                                        <span><?php echo esc_html( $hh_person['is_you'] ? __( 'you', 'households' ) : $hh_person['name'] ); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="actions" style="padding-left:8px">
                                                <button class="primary" type="submit">
                                                    <?php
                                                    echo $hh_at
                                                        ? esc_html__( 'Say they are here', 'households' )
                                                        : esc_html__( 'Move here', 'households' );
                                                    ?>
                                                </button>
                                                <?php // What you said about today, taken back: the pattern answers again, or nothing does. The button names the same field as the hidden one above it and is posted after it, so what it says is what arrives. ?>
                                                <?php if ( $hh_at && $hh_where['said'] ) : ?>
                                                    <button type="submit" name="said_home_id" value="0" class="quiet">
                                                        <?php echo $hh_where['rotates'] ? esc_html__( 'Back to the pattern', 'households' ) : esc_html__( 'Elsewhere', 'households' ); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    <?php else : ?>
                                        <p class="meta" style="padding-left:8px"><?php echo esc_html__( 'There is nobody here you can say a day for.', 'households' ); ?></p>
                                    <?php endif; ?>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section id="hh-agenda" data-hh-live-section>
                    <h2><a href="<?php echo esc_url( View::base() . 'where/' ); ?>"><?php echo esc_html__( 'Who is where', 'households' ); ?></a></h2>
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
                                /* translators: 1: a list of names, 2: the household they leave, 3: the household they arrive at. */
                                $hh_line = sprintf(
                                    __( '%1$s: %2$s to %3$s', 'households' ),
                                    implode( ', ', $hh_entry['people'] ),
                                    $hh_entry['from_name'],
                                    $hh_entry['home_name']
                                );
                            } else {
                                /* translators: %s: a name. */
                                $hh_meta = $hh_entry['who'] ? sprintf( __( 'for %s', 'households' ), $hh_entry['who'] ) : __( 'for the household', 'households' );
                                if ( 'appointment' === $hh_entry['kind'] ) {
                                    $hh_meta .= ' · ' . __( 'appointment', 'households' );
                                }
                            }
                            ?>
                            <li class="row">
                                <div class="grow">
                                    <?php // A move is a day on the board, so its line is the way to it: the fortnight, read from the household being arrived at. ?>
                                    <?php if ( 'move' === $hh_entry['kind'] && $hh_entry['home_id'] ) : ?>
                                        <strong><a href="<?php echo esc_url( add_query_arg( 'home', $hh_entry['home_id'], View::base() . 'where/' ) ); ?>"><?php echo esc_html( $hh_line ); ?></a></strong>
                                    <?php else : ?>
                                        <strong><?php echo esc_html( $hh_line ); ?></strong>
                                    <?php endif; ?>
                                    <?php if ( $hh_meta ) : ?>
                                        <div class="meta"><?php echo esc_html( $hh_meta ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="actions">
                                    <?php // A move names both households in its line; the pill would say it twice. ?>
                                    <?php if ( $hh_entry['home_id'] && 'move' !== $hh_entry['kind'] ) : ?>
                                        <a class="pill" style="text-decoration:none" href="<?php echo esc_url( View::home_url( $hh_entry['home_id'] ) ); ?>">
                                            <?php
                                            /* translators: %s: the name of a household. */
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
            </div>
        </div>
        <?php endif; ?>

<?php if ( $hh_homes ) : ?>
    <?php require __DIR__ . '/_todo-script.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
