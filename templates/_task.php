<?php
/**
 * One task, tickable where it is read. The household is the one the list is of,
 * so the line says who it is for instead of saying the household again on every
 * row. Ticked, it stays here struck through, with the tick still in the box:
 * the way to take it back is the way it was done.
 *
 * Expects $hh_task and $hh_task_home.
 */

namespace Households;

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
        <?php if ( $hh_overdue ) : ?>
            <span class="pill warm"><?php echo esc_html__( 'overdue', 'households' ); ?></span>
        <?php endif; ?>
        <?php // Whose it is. Nothing named is nobody's, which the empty space says as well as a word would. ?>
        <?php if ( $hh_task['person'] ) : ?>
            <span class="pill"><?php echo esc_html( $hh_task['person'] ); ?></span>
        <?php endif; ?>
    </div>
</li>
