<?php
/**
 * A person, and what travels with them between homes.
 */

namespace Households;

$hh_person_id = (int) get_query_var( 'person_id' );
$hh_user = View::user_id();
$hh_allowed = Access::can_view_person( $hh_user, $hh_person_id );
$hh_person = $hh_allowed ? View::storage()->get_person( $hh_person_id ) : [];

require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Your day', 'households' ); ?></a>
        <?php if ( ! $hh_person ) : ?>
            <h1><?php echo esc_html__( 'A person', 'households' ); ?></h1>
            <p class="subtitle"><?php echo esc_html__( 'There is nobody here you are allowed to look at.', 'households' ); ?></p>
            <?php require __DIR__ . '/_foot.php'; ?>
            <?php return; ?>
        <?php endif; ?>

        <h1><?php echo esc_html( $hh_person['name'] ); ?></h1>
        <p class="subtitle">
            <?php
            $hh_bits = [];
            $hh_bits[] = $hh_person['homes']
                ? __( 'Belongs to:', 'households' ) . ' ' . implode( ', ', wp_list_pluck( $hh_person['homes'], 'name' ) )
                : __( 'Not in any home.', 'households' );
            if ( null !== $hh_person['age'] ) {
                /* translators: %d: an age in years. */
                $hh_bits[] = sprintf( __( '%d years old', 'households' ), $hh_person['age'] );
            }
            if ( ! $hh_person['user_id'] ) {
                $hh_bits[] = __( 'No account — nobody logs in as them.', 'households' );
            }
            echo esc_html( implode( ' · ', $hh_bits ) );
            ?>
        </p>
        <?php View::notice(); ?>

        <section>
            <h2><?php echo esc_html__( 'What travels with them', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'Sizes, allergies, medication, whatever the next person needs to know. Every home they belong to reads this same page, and every edit is kept, so a size written down with a date still says something in a year.', 'households' ); ?></p>
            <form method="post" style="display:grid;gap:10px">
                <?php View::fields( 'save_person', [ 'person_id' => $hh_person['id'] ] ); ?>
                <label><?php echo esc_html__( 'Born', 'households' ); ?>
                    <input type="date" name="birthdate" value="<?php echo esc_attr( $hh_person['birthdate'] ); ?>">
                </label>
                <label><?php echo esc_html__( 'About', 'households' ); ?>
                    <small><?php echo esc_html__( 'Prose, not fields. Date what changes: “shoe size 32 — March 2026”.', 'households' ); ?></small>
                    <textarea name="about"><?php echo esc_textarea( $hh_person['about'] ); ?></textarea>
                </label>
                <div>
                    <button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button>
                    <span class="meta"><?php echo esc_html__( 'The previous version is kept.', 'households' ); ?></span>
                </div>
            </form>
        </section>
<?php require __DIR__ . '/_foot.php'; ?>
