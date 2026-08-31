<?php
/**
 * One open task on the front page, tickable from there. The task names its own
 * home, because this page is about none of them.
 *
 * Expects $hh_task.
 */

namespace Households;

$hh_bits = [];
if ( $hh_task['due_label'] ) {
    $hh_bits[] = $hh_task['due_label'];
}
if ( 'appointment' === $hh_task['task_type'] ) {
    $hh_bits[] = __( 'appointment', 'households' );
}
?>
<li class="row">
    <form method="post" class="actions grow">
        <?php View::fields( 'toggle_task', [ 'home_id' => $hh_task['home_id'], 'task_id' => $hh_task['id'] ] ); ?>
        <button type="submit" class="quiet"><?php echo esc_html__( 'Done', 'households' ); ?></button>
        <span>
            <?php echo esc_html( $hh_task['title'] ); ?>
            <?php if ( $hh_bits ) : ?>
                <span class="meta">· <?php echo esc_html( implode( ' · ', $hh_bits ) ); ?></span>
            <?php endif; ?>
        </span>
    </form>
    <div class="actions">
        <?php if ( $hh_task['is_overdue'] ) : ?>
            <span class="pill warm"><?php echo esc_html__( 'overdue', 'households' ); ?></span>
        <?php endif; ?>
        <a class="pill" style="text-decoration:none" href="<?php echo esc_url( View::home_url( $hh_task['home_id'] ) ); ?>">
            <?php
            /* translators: %s: the name of a home. */
            echo esc_html( sprintf( __( 'at %s', 'households' ), $hh_task['home_name'] ) );
            ?>
        </a>
    </div>
</li>
