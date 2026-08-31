<?php
/**
 * One task, tickable where it is read, and — for whoever writes here — the
 * same four answers again when it is wrong. The household is the one the list
 * is of, so the line says who it is for instead of saying the household again
 * on every row. Ticked, it stays here struck through, with the tick still in
 * the box: the way to take it back is the way it was done.
 *
 * Expects $hh_task and $hh_task_home. A page that lets it be written wants
 * $hh_task_url (the page, without anything a form left behind), $hh_task_people
 * (who it could be for), $hh_task_writing and $hh_task_editing (which row is
 * open, if any).
 */

namespace Households;

$hh_task_url = isset( $hh_task_url ) ? $hh_task_url : remove_query_arg( 'problem' );
$hh_task_people = isset( $hh_task_people ) ? $hh_task_people : [];
$hh_task_writing = ! empty( $hh_task_writing );
// Editing is a page you can be sent, reload and come back to, so it is in the
// URL. Closing it is the same page without it, and that is where the form
// posts: saved, the row is a line again without anyone asking twice.
$hh_task_shut = remove_query_arg( 'edit', $hh_task_url );
$hh_task_open = add_query_arg( 'edit', $hh_task['id'], $hh_task_shut );
// Said into a name of its own: which row is open outlives this one row, and
// the list asks the partial about the next one straight after.
$hh_task_is_open = $hh_task_writing && isset( $hh_task_editing ) && (int) $hh_task_editing === $hh_task['id'];

$hh_bits = [];
if ( $hh_task['due_date'] ) {
    $hh_bits[] = View::date( $hh_task['due_date'] );
}
if ( 'appointment' === $hh_task['task_type'] ) {
    $hh_bits[] = __( 'appointment', 'households' );
}
// Nothing done is late, whenever it was due.
$hh_overdue = ! $hh_task['is_done'] && $hh_task['due_date'] && $hh_task['due_date'] < current_time( 'Y-m-d' );
?>
<?php if ( $hh_task_is_open ) : ?>
    <li class="row">
        <form method="post" class="grid grow" action="<?php echo esc_url( $hh_task_shut ); ?>">
            <?php View::fields( 'edit_task', [ 'home_id' => $hh_task_home, 'task_id' => $hh_task['id'] ] ); ?>
            <label class="wide"><?php echo esc_html__( 'What needs doing', 'households' ); ?>
                <input type="text" name="title" value="<?php echo esc_attr( $hh_task['title'] ); ?>" required autofocus>
            </label>
            <label><?php echo esc_html__( 'For whom', 'households' ); ?>
                <select name="person_id">
                    <option value="0"><?php echo esc_html__( 'Everyone', 'households' ); ?></option>
                    <?php foreach ( $hh_task_people as $hh_person ) : ?>
                        <option value="<?php echo (int) $hh_person['id']; ?>" <?php selected( $hh_person['id'], $hh_task['person_id'] ); ?>>
                            <?php echo esc_html( $hh_person['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?php echo esc_html__( 'Kind', 'households' ); ?>
                <select name="task_type">
                    <option value="task" <?php selected( 'task', $hh_task['task_type'] ); ?>><?php echo esc_html__( 'Task', 'households' ); ?></option>
                    <option value="appointment" <?php selected( 'appointment', $hh_task['task_type'] ); ?>><?php echo esc_html__( 'Appointment', 'households' ); ?></option>
                </select>
            </label>
            <label><?php echo esc_html__( 'When', 'households' ); ?>
                <input type="date" name="due_date" value="<?php echo esc_attr( $hh_task['due_date'] ); ?>">
            </label>
            <div class="actions wide">
                <button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button>
                <a class="pill" data-hh-live href="<?php echo esc_url( $hh_task_shut ); ?>"><?php echo esc_html__( 'cancel', 'households' ); ?></a>
            </div>
        </form>
        <?php // Its own form, because it is its own verb and its own nonce; the row it belongs to is open, which is the only place it is offered. ?>
        <form method="post" action="<?php echo esc_url( $hh_task_shut ); ?>">
            <?php View::fields( 'remove_task', [ 'home_id' => $hh_task_home, 'task_id' => $hh_task['id'] ] ); ?>
            <button type="submit" class="quiet"><?php echo esc_html__( 'Remove', 'households' ); ?></button>
        </form>
    </li>
<?php else : ?>
    <li class="row">
        <form method="post" class="actions grow">
            <?php View::fields( 'toggle_task', [ 'home_id' => $hh_task_home, 'task_id' => $hh_task['id'] ] ); ?>
            <?php // Ticking it off is a tick, and unticking it is the same tick again. The button behind it is what does the ticking when there is no script to notice the box. ?>
            <label class="inline">
                <input type="checkbox" data-hh-tick <?php checked( $hh_task['is_done'] ); ?>>
                <span class="<?php echo $hh_task['is_done'] ? 'done' : ''; ?>">
                    <?php echo esc_html( $hh_task['title'] ); ?>
                    <?php if ( $hh_bits ) : ?>
                        <span class="meta">· <?php echo esc_html( implode( ' · ', $hh_bits ) ); ?></span>
                    <?php endif; ?>
                </span>
            </label>
            <button type="submit" class="quiet" data-hh-fallback>
                <?php echo $hh_task['is_done'] ? esc_html__( 'Undo', 'households' ) : esc_html__( 'Done', 'households' ); ?>
            </button>
        </form>
        <div class="actions">
            <?php // Waiting to be pointed at: a list is for reading, and only the line under the cursor is being thought about. ?>
            <?php if ( $hh_task_writing ) : ?>
                <a class="pill onhover" data-hh-live href="<?php echo esc_url( $hh_task_open ); ?>"
                    aria-label="<?php echo esc_attr( sprintf(
                        /* translators: %s: what a task says. */
                        __( 'Edit “%s”', 'households' ),
                        $hh_task['title']
                    ) ); ?>"><?php echo esc_html__( 'edit', 'households' ); ?></a>
            <?php endif; ?>
            <?php if ( $hh_overdue ) : ?>
                <span class="pill warm"><?php echo esc_html__( 'overdue', 'households' ); ?></span>
            <?php endif; ?>
            <?php // Whose it is. Nothing named is nobody's, which the empty space says as well as a word would. ?>
            <?php if ( $hh_task['person'] ) : ?>
                <span class="pill"><?php echo esc_html( $hh_task['person'] ); ?></span>
            <?php endif; ?>
        </div>
    </li>
<?php endif; ?>
