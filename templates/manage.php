<?php
/**
 * Managing one home: who is in it, who administers it, what it is called.
 */

namespace Households;

$hh_home_id = (int) get_query_var( 'id' );
$hh_user = View::user_id();
$hh = View::storage()->get_dashboard( $hh_user, $hh_home_id );

require __DIR__ . '/_head.php';

if ( ! $hh ) {
    require __DIR__ . '/_foot.php';
    return;
}
?>
        <a class="back" href="<?php echo esc_url( View::base() ); ?>">&larr; <?php echo esc_html__( 'Your day', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Manage this home', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html( $hh['home']['name'] ); ?></p>
        <?php View::notice(); ?>

        <section>
            <h2><?php echo esc_html__( 'Name', 'households' ); ?></h2>
            <form method="post" class="grid">
                <?php View::fields( 'update_home' ); ?>
                <label class="wide"><?php echo esc_html__( 'What this home is called', 'households' ); ?>
                    <input type="text" name="name" required value="<?php echo esc_attr( $hh['home']['name'] ); ?>">
                </label>
                <button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button>
            </form>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Who is in it', 'households' ); ?></h2>
            <ul class="plain">
                <?php if ( ! $hh['people'] ) : ?>
                    <li class="empty"><?php echo esc_html__( 'Nobody here yet.', 'households' ); ?></li>
                <?php endif; ?>
                <?php foreach ( $hh['people'] as $hh_person ) : ?>
                    <?php
                    $hh_is_self = $hh_person['id'] === $hh['viewer']['person_id'];
                    $hh_is_admin = in_array( $hh_person['id'], $hh['admins'], true );
                    ?>
                    <li class="row">
                        <div class="grow">
                            <strong>
                                <?php
                                echo esc_html( $hh_person['name'] );
                                echo $hh_is_self ? ' (' . esc_html__( 'You', 'households' ) . ')' : '';
                                ?>
                            </strong>
                            <div class="actions" style="margin:4px 0">
                                <?php if ( $hh_person['is_child'] ) : ?>
                                    <span class="pill"><?php echo esc_html__( 'Child', 'households' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $hh_person['label'] ) : ?>
                                    <span class="pill"><?php echo esc_html( $hh_person['label'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( $hh_is_admin ) : ?>
                                    <span class="pill"><?php echo esc_html__( 'Administers this home', 'households' ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! $hh_person['user_id'] ) : ?>
                                    <span class="pill warm"><?php echo esc_html__( 'No account', 'households' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="actions">
                                <?php
                                View::fields( 'update_person', [
                                    'person_id' => $hh_person['id'],
                                    'label'     => $hh_person['label'],
                                    'is_child'  => $hh_person['is_child'] ? 0 : 1,
                                ] );
                                ?>
                                <button type="submit" class="quiet">
                                    <?php echo $hh_person['is_child'] ? esc_html__( 'Not a child', 'households' ) : esc_html__( 'Is a child', 'households' ); ?>
                                </button>
                            </form>
                        </div>
                        <div class="actions" style="align-items:flex-start">
                            <a class="button quiet" href="<?php echo esc_url( View::person_url( $hh_person['id'] ) ); ?>"><?php echo esc_html__( 'Open page', 'households' ); ?></a>
                            <?php // Someone with no account has nothing to administer with, and an administrator may not drop themselves and leave nobody. ?>
                            <?php if ( $hh_person['user_id'] && ! $hh_is_self ) : ?>
                                <form method="post">
                                    <?php View::fields( 'set_admin', [ 'person_id' => $hh_person['id'], 'is_admin' => $hh_is_admin ? 0 : 1 ] ); ?>
                                    <button type="submit" class="quiet">
                                        <?php echo $hh_is_admin ? esc_html__( 'Remove as administrator', 'households' ) : esc_html__( 'Make administrator', 'households' ); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ( ! $hh_is_self ) : ?>
                                <form method="post" onsubmit="return confirm(this.dataset.confirm)"
                                    data-confirm="<?php
                                    /* translators: %s: a name. */
                                    echo esc_attr( sprintf( __( 'Remove %s from this home? Their record and everything written on it stays.', 'households' ), $hh_person['name'] ) );
                                    ?>">
                                    <?php View::fields( 'remove_person', [ 'person_id' => $hh_person['id'] ] ); ?>
                                    <button type="submit" class="quiet"><?php echo esc_html__( 'Remove from this home', 'households' ); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Add someone', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'An email address gives them an account they can log in with. Leave it empty for someone who will never log in — a small child, or a relative you are only keeping notes about.', 'households' ); ?></p>
            <form method="post" class="grid">
                <?php View::fields( 'add_person' ); ?>
                <label><?php echo esc_html__( 'Name', 'households' ); ?><input type="text" name="name" required></label>
                <label><?php echo esc_html__( 'Email (optional)', 'households' ); ?><input type="email" name="email"></label>
                <label><?php echo esc_html__( 'Password (optional)', 'households' ); ?><input type="text" name="password" autocomplete="off"></label>
                <label><?php echo esc_html__( 'Called', 'households' ); ?>
                    <input type="text" name="label" placeholder="<?php echo esc_attr__( 'Grandparent', 'households' ); ?>">
                </label>
                <label><?php echo esc_html__( 'Born', 'households' ); ?><input type="date" name="birthdate"></label>
                <label class="inline"><input type="checkbox" name="is_child" value="1"> <?php echo esc_html__( 'Is a child', 'households' ); ?></label>
                <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
            </form>
        </section>
<?php require __DIR__ . '/_foot.php'; ?>
