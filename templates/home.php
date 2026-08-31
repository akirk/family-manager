<?php
/**
 * One home: its people, what needs doing, what the house needs you to know,
 * and what is kept there. Everything on it is written down in this home, so
 * every form here posts back to this page.
 */

namespace Households;

$hh_home_id = (int) get_query_var( 'id' );
$hh_user = View::user_id();
$hh_subject = App::subject_for_page( $hh_user );
$hh = View::storage()->get_dashboard( $hh_user, $hh_home_id, $hh_subject );

$hh_title = $hh ? $hh['home']['name'] : __( 'A household', 'households' );

require __DIR__ . '/_head.php';

if ( ! $hh ) {
    require __DIR__ . '/_foot.php';
    return;
}

$hh_can_organise = $hh['viewer']['can_organise'];
$hh_writing = $hh_can_organise && ! $hh['viewer']['viewing_as'];

// The list reads the way it reads on the overview, and asks the same three
// things of the URL: what was ticked off before this week, whether the form for
// a new one is open, and which row is open to be written.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$hh_earlier = ! empty( $_GET['earlier'] );
$hh_adding = ! empty( $_GET['add'] );
$hh_editing = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$hh_url = remove_query_arg( 'problem' );
$hh_open = add_query_arg( 'add', 1, $hh_url );
$hh_close = remove_query_arg( 'add', $hh_url );
$hh_sifted = Storage::sift_tasks( $hh['tasks'], $hh_earlier );
$hh_tasks = $hh_sifted['tasks'];
$hh_quiet = $hh_sifted['quiet'];
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Overview', 'households' ); ?></a>
        <h1><?php echo esc_html( $hh['home']['name'] ); ?></h1>
        <p class="subtitle">
            <?php
            $hh_here = wp_list_pluck( $hh['here'], 'name' );
            echo $hh_here
                ? esc_html__( 'Here today:', 'households' ) . ' ' . esc_html( implode( ', ', $hh_here ) ) . '.'
                : esc_html__( 'Nobody here today.', 'households' );

            if ( $hh['unknown'] ) {
                echo ' ' . esc_html( sprintf(
                    /* translators: %s: a list of names. */
                    __( '%s could be anywhere today.', 'households' ),
                    implode( ', ', wp_list_pluck( $hh['unknown'], 'name' ) )
                ) );
            }
            ?>
        </p>
        <?php View::notice(); ?>

        <?php if ( $hh['viewer']['viewing_as'] ) : ?>
            <section>
                <strong>
                    <?php
                    /* translators: %s: a name. */
                    echo esc_html( sprintf( __( 'You are looking at this household as %s sees it.', 'households' ), $hh['subject']['name'] ) );
                    ?>
                </strong>
            </section>
        <?php endif; ?>

        <section id="hh-todo" data-hh-live-section>
            <div class="row heading">
                <h2><?php echo esc_html__( 'To do', 'households' ); ?></h2>
                <div class="actions">
                    <?php // Offered while there is a list to filter; an empty one says where the rest went in words instead. ?>
                    <?php if ( ( $hh_tasks && $hh_quiet ) || $hh_earlier ) : ?>
                        <a class="pill" data-hh-live
                            href="<?php echo esc_url( $hh_earlier ? remove_query_arg( 'earlier', $hh_url ) : add_query_arg( 'earlier', 1, $hh_url ) ); ?>">
                            <?php echo $hh_earlier ? esc_html__( 'the past week', 'households' ) : esc_html__( 'done earlier', 'households' ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( $hh_writing ) : ?>
                        <?php // A link that asks the page for the form; the script opens the one already here instead. ?>
                        <a class="pill" data-hh-add href="<?php echo esc_url( $hh_adding ? $hh_close : $hh_open ); ?>"
                            data-hh-open="<?php echo esc_url( $hh_open ); ?>" data-hh-close="<?php echo esc_url( $hh_close ); ?>"
                            aria-controls="add" aria-expanded="<?php echo $hh_adding ? 'true' : 'false'; ?>"
                            aria-label="<?php echo esc_attr__( 'Write something down', 'households' ); ?>"><?php echo $hh_adding ? '&times;' : '+'; ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $hh_writing ) : ?>
                <form method="post" class="grid" id="add" style="margin-bottom:12px" <?php echo $hh_adding ? '' : 'hidden'; ?>>
                    <?php View::fields( 'add_task', [ 'home_id' => $hh_home_id ] ); ?>
                    <label class="wide"><?php echo esc_html__( 'What needs doing', 'households' ); ?>
                        <input type="text" name="title" required <?php echo $hh_adding ? 'autofocus' : ''; ?>>
                    </label>
                    <label><?php echo esc_html__( 'For whom', 'households' ); ?>
                        <select name="person_id">
                            <option value="0"><?php echo esc_html__( 'Everyone', 'households' ); ?></option>
                            <?php foreach ( $hh['people'] as $hh_person ) : ?>
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
                <?php if ( ! $hh_tasks ) : ?>
                    <li class="empty">
                        <?php echo esc_html__( 'Nothing to do.', 'households' ); ?>
                        <?php // An empty list with something behind it says where the rest went; this is the one place you would look for it. ?>
                        <?php if ( $hh_quiet && ! $hh_earlier ) : ?>
                            <a data-hh-live href="<?php echo esc_url( add_query_arg( 'earlier', 1, $hh_url ) ); ?>"><?php echo esc_html__( 'See what was done earlier.', 'households' ); ?></a>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
                <?php
                $hh_task_home = $hh_home_id;
                $hh_task_url = $hh_url;
                $hh_task_people = $hh['people'];
                $hh_task_writing = $hh_writing;
                $hh_task_editing = $hh_editing;
                ?>
                <?php foreach ( $hh_tasks as $hh_task ) : ?>
                    <?php require __DIR__ . '/_task.php'; ?>
                <?php endforeach; ?>
            </ul>

        </section>

        <section>
            <div class="row heading">
                <h2><?php echo esc_html__( 'About this household', 'households' ); ?></h2>
                <?php if ( $hh_writing ) : ?>
                    <details class="add">
                        <summary><?php echo esc_html__( '+ Add', 'households' ); ?></summary>
                        <form method="post" class="grid">
                            <?php View::fields( 'add_note', [ 'kind' => 'fact' ] ); ?>
                            <label><?php echo esc_html__( 'Label', 'households' ); ?><input type="text" name="title" required></label>
                            <label class="wide"><?php echo esc_html__( 'Detail', 'households' ); ?><input type="text" name="detail"></label>
                            <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                        </form>
                    </details>
                <?php elseif ( ! $hh['facts'] ) : ?>
                    <span class="meta"><?php echo esc_html__( 'Nothing written down yet.', 'households' ); ?></span>
                <?php endif; ?>
            </div>
            <ul class="plain">
                <?php foreach ( $hh['facts'] as $hh_note ) : ?>
                    <li class="row">
                        <div class="grow">
                            <strong><?php echo esc_html( $hh_note['title'] ); ?></strong>
                            <div class="meta"><?php echo esc_html( $hh_note['detail'] ); ?></div>
                        </div>
                        <?php if ( $hh_writing ) : ?>
                            <form method="post">
                                <?php View::fields( 'remove_note', [ 'kind' => 'fact', 'note_id' => $hh_note['id'] ] ); ?>
                                <button type="submit" class="quiet"><?php echo esc_html__( 'Remove', 'households' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        </section>

        <?php // Ticking something off the packing is a tick like any other, so the section it is in comes back from the server rather than the page going away and returning. ?>
        <section id="hh-things" data-hh-live-section>
            <div class="row heading">
                <h2><?php echo esc_html__( 'Things kept here', 'households' ); ?></h2>
                <?php if ( $hh_writing ) : ?>
                    <details class="add">
                        <summary><?php echo esc_html__( '+ Add', 'households' ); ?></summary>
                        <form method="post" class="grid">
                            <?php View::fields( 'add_note', [ 'kind' => 'item' ] ); ?>
                            <label><?php echo esc_html__( 'Thing', 'households' ); ?><input type="text" name="title" required></label>
                            <label class="wide"><?php echo esc_html__( 'Where it lives', 'households' ); ?><input type="text" name="detail"></label>
                            <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                        </form>
                    </details>
                <?php elseif ( ! $hh['items'] ) : ?>
                    <span class="meta"><?php echo esc_html__( 'Nothing listed yet.', 'households' ); ?></span>
                <?php endif; ?>
            </div>
            <?php require __DIR__ . '/_undone.php'; ?>
            <?php // The same line the things pages print, because a thing reads the same wherever it is listed: where it lives, who else keeps it, where it has got to, and where it is to go. The heading has said which household this is. ?>
            <ul class="plain">
                <?php $hh_homes = $hh['homes']; ?>
                <?php foreach ( $hh['items'] as $hh_thing ) : ?>
                    <?php
                    $hh_thing_home = $hh['home']['id'];
                    $hh_thing_at_said = true;
                    $hh_thing_writing = $hh_writing;
                    $hh_thing_going_said = false;
                    ?>
                    <?php require __DIR__ . '/_thing.php'; ?>
                <?php endforeach; ?>
            </ul>

            <?php // Things this house does not keep that somebody has said are here: brought along for the weekend, borrowed, left behind. They belong where they belong, so they are said apart from what is kept here. ?>
            <?php if ( $hh['on_loan'] ) : ?>
                <h3 style="margin:14px 0 6px;font-size:0.95rem"><?php echo esc_html__( 'Here just now', 'households' ); ?></h3>
                <ul class="plain">
                    <?php foreach ( $hh['on_loan'] as $hh_lent ) : ?>
                        <?php
                        // Only the households of yours that keep it are named,
                        // and only those you write in are offered to send it
                        // back to. That another family keeps it too is not
                        // yours to be told.
                        $hh_keepers = [];
                        $hh_back = [];
                        foreach ( $hh_lent['homes'] as $hh_other ) {
                            if ( ! Access::can_reach( $hh_user, $hh_other['id'] ) ) {
                                continue;
                            }
                            $hh_keepers[] = $hh_other['name'];
                            if ( current_user_can( 'organise_household', $hh_other['id'] ) ) {
                                $hh_back[] = $hh_other;
                            }
                        }
                        ?>
                        <li class="row">
                            <div class="grow">
                                <?php // Its page is the keeping houses' to open. Somebody who belongs to this one alone can see that it is here, which is what the list is for, and no more than that. ?>
                                <strong>
                                    <?php if ( $hh_keepers ) : ?>
                                        <a href="<?php echo esc_url( View::thing_url( $hh_lent['id'] ) ); ?>"><?php echo esc_html( $hh_lent['title'] ); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html( $hh_lent['title'] ); ?>
                                    <?php endif; ?>
                                </strong>
                                <div class="meta">
                                    <?php
                                    echo $hh_keepers
                                        ? esc_html( sprintf(
                                            /* translators: %s: a list of household names. */
                                            __( 'Kept at %s.', 'households' ),
                                            implode( ', ', $hh_keepers )
                                        ) )
                                        : esc_html__( 'Kept somewhere that is not yours.', 'households' );
                                    ?>
                                </div>
                            </div>
                            <?php if ( $hh_back ) : ?>
                                <form method="post" class="actions">
                                    <?php View::fields( 'note_is_at', [ 'kind' => 'item', 'note_id' => $hh_lent['id'] ] ); ?>
                                    <?php foreach ( $hh_back as $hh_other ) : ?>
                                        <button type="submit" class="quiet" name="home_id" value="<?php echo (int) $hh_other['id']; ?>">
                                            <?php
                                            /* translators: %s: the name of a household. */
                                            echo esc_html( sprintf( __( 'Back at %s', 'households' ), $hh_other['name'] ) );
                                            ?>
                                        </button>
                                    <?php endforeach; ?>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </section>

        <?php if ( $hh['birthdays'] ) : ?>
            <section>
                <h2><?php echo esc_html__( 'Birthdays coming up', 'households' ); ?></h2>
                <ul class="plain">
                    <?php foreach ( array_slice( $hh['birthdays'], 0, 5 ) as $hh_birthday ) : ?>
                        <li>
                            <?php
                            printf(
                                esc_html(
                                    /* translators: 1: a name, 2: an age, 3: a number of days. */
                                    __( '%1$s turns %2$d in %3$d days', 'households' )
                                ),
                                esc_html( $hh_birthday['name'] ),
                                (int) $hh_birthday['turning'],
                                (int) $hh_birthday['days_until']
                            );
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

<?php require __DIR__ . '/_todo-script.php'; ?>
<?php require __DIR__ . '/_foot.php'; ?>
