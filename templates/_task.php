<?php
/**
 * One open task, tickable where it is read. The household is the one the list
 * is of, so the line says who it is for instead of saying the household again
 * on every row.
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
$hh_overdue = $hh_task['due_date'] && $hh_task['due_date'] < current_time( 'Y-m-d' );
?>
<li class="row">
    <form method="post" class="actions grow">
        <?php View::fields( 'toggle_task', [ 'home_id' => $hh_task_home, 'task_id' => $hh_task['id'] ] ); ?>
        <?php // Ticking it off is a tick. The button behind it is what does the ticking when there is no script to notice the box. ?>
        <label class="inline">
            <input type="checkbox" data-hh-tick>
            <span>
                <?php echo esc_html( $hh_task['title'] ); ?>
                <?php if ( $hh_bits ) : ?>
                    <span class="meta">· <?php echo esc_html( implode( ' · ', $hh_bits ) ); ?></span>
                <?php endif; ?>
            </span>
        </label>
        <button type="submit" class="quiet" data-hh-fallback><?php echo esc_html__( 'Done', 'households' ); ?></button>
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
