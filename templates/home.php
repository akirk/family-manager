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
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Your day', 'households' ); ?></a>
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

        <section>
            <h2><?php echo esc_html__( 'To do', 'households' ); ?></h2>
            <ul class="plain">
                <?php if ( ! $hh['tasks'] ) : ?>
                    <li class="empty"><?php echo esc_html__( 'Nothing to do.', 'households' ); ?></li>
                <?php endif; ?>
                <?php foreach ( $hh['tasks'] as $hh_task ) : ?>
                    <?php
                    $hh_bits = [ $hh_task['person'] ? $hh_task['person'] : __( 'Everyone', 'households' ) ];
                    if ( $hh_task['due_date'] ) {
                        $hh_bits[] = View::date( $hh_task['due_date'] );
                    }
                    if ( 'appointment' === $hh_task['task_type'] ) {
                        $hh_bits[] = __( 'Appointment', 'households' );
                    }
                    ?>
                    <li class="row">
                        <form method="post" class="actions grow">
                            <?php View::fields( 'toggle_task', [ 'task_id' => $hh_task['id'] ] ); ?>
                            <button type="submit" class="quiet">
                                <?php echo $hh_task['is_done'] ? esc_html__( 'Undo', 'households' ) : esc_html__( 'Done', 'households' ); ?>
                            </button>
                            <span class="<?php echo $hh_task['is_done'] ? 'done' : ''; ?>">
                                <?php echo esc_html( $hh_task['title'] ); ?>
                                <span class="meta">· <?php echo esc_html( implode( ' · ', $hh_bits ) ); ?></span>
                            </span>
                        </form>
                        <?php if ( $hh_writing ) : ?>
                            <form method="post">
                                <?php View::fields( 'remove_task', [ 'task_id' => $hh_task['id'] ] ); ?>
                                <button type="submit" class="quiet"><?php echo esc_html__( 'Remove', 'households' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ( $hh_writing ) : ?>
                <form method="post" class="grid" style="margin-top:12px">
                    <?php View::fields( 'add_task' ); ?>
                    <label class="wide"><?php echo esc_html__( 'What needs doing', 'households' ); ?>
                        <input type="text" name="title" required>
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
        </section>

        <section>
            <h2><?php echo esc_html__( 'About this household', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'What the household needs you to know: the wifi, where the water main valve is, which day the bins go out.', 'households' ); ?></p>
            <ul class="plain">
                <?php if ( ! $hh['facts'] ) : ?>
                    <li class="empty"><?php echo esc_html__( 'Nothing written down yet.', 'households' ); ?></li>
                <?php endif; ?>
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

            <?php if ( $hh_writing ) : ?>
                <form method="post" class="grid" style="margin-top:12px">
                    <?php View::fields( 'add_note', [ 'kind' => 'fact' ] ); ?>
                    <label><?php echo esc_html__( 'Label', 'households' ); ?><input type="text" name="title" required></label>
                    <label class="wide"><?php echo esc_html__( 'Detail', 'households' ); ?><input type="text" name="detail"></label>
                    <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                </form>
            <?php endif; ?>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Things kept here', 'households' ); ?></h2>
            <ul class="plain">
                <?php if ( ! $hh['items'] ) : ?>
                    <li class="empty"><?php echo esc_html__( 'Nothing listed yet.', 'households' ); ?></li>
                <?php endif; ?>
                <?php foreach ( $hh['items'] as $hh_note ) : ?>
                    <li class="row">
                        <div class="grow">
                            <strong><?php echo esc_html( $hh_note['title'] ); ?></strong>
                            <div class="meta"><?php echo esc_html( $hh_note['detail'] ); ?></div>
                        </div>
                        <?php if ( $hh_writing ) : ?>
                            <?php // A thing is somewhere rather than nowhere: it moves to another home instead of being taken off the list. ?>
                            <form method="post" class="actions">
                                <?php View::fields( 'move_note', [ 'kind' => 'item', 'note_id' => $hh_note['id'] ] ); ?>
                                <?php foreach ( $hh['homes'] as $hh_other ) : ?>
                                    <?php if ( $hh_other['id'] !== $hh['home']['id'] ) : ?>
                                        <button type="submit" class="quiet" name="target_home_id" value="<?php echo (int) $hh_other['id']; ?>">
                                            <?php
                                            /* translators: %s: the name of a household. */
                                            echo esc_html( sprintf( __( 'Move to %s', 'households' ), $hh_other['name'] ) );
                                            ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ( $hh_writing ) : ?>
                <form method="post" class="grid" style="margin-top:12px">
                    <?php View::fields( 'add_note', [ 'kind' => 'item' ] ); ?>
                    <label><?php echo esc_html__( 'Thing', 'households' ); ?><input type="text" name="title" required></label>
                    <label class="wide"><?php echo esc_html__( 'Where it lives', 'households' ); ?><input type="text" name="detail"></label>
                    <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                </form>
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
<?php require __DIR__ . '/_foot.php'; ?>
