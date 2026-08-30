<?php
/**
 * Every home you belong to, and where a new one is started. A home with
 * nobody in it is not a home, so starting one puts you in it.
 */
require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Your day', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Your homes', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Every home you belong to, and who is under each roof today.', 'households' ); ?></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>

        <section>
            <ul class="plain" data-homes></ul>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Start a home', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'A house, a flat, a grandparent’s spare room — anywhere the family keeps things and people. You will be the first person in it and the one who administers it; everyone else is added from the home itself, which is where this takes you.', 'households' ); ?></p>
            <form data-start class="grid">
                <label class="wide"><?php echo esc_html__( 'What is it called?', 'households' ); ?>
                    <input type="text" name="name" required maxlength="80" placeholder="<?php echo esc_attr__( 'Home', 'households' ); ?>">
                </label>
                <div><button class="primary" type="submit"><?php echo esc_html__( 'Start it', 'households' ); ?></button></div>
            </form>
        </section>
    <script>
        (function() {
            const t = {
                none: '<?php echo esc_js( __( 'You do not belong to a home yet. Start one below and it is yours.', 'households' ) ); ?>',
                here: '<?php echo esc_js( __( 'Here today:', 'households' ) ); ?>',
                nobody: '<?php echo esc_js( __( 'Nobody here today.', 'households' ) ); ?>',
                open: '<?php echo esc_js( __( 'Open', 'households' ) ); ?>',
                manage: '<?php echo esc_js( __( 'Manage', 'households' ) ); ?>',
                task: '<?php echo esc_js( __( '1 thing to do', 'households' ) ); ?>',
                tasks: '<?php echo esc_js( __( '%d things to do', 'households' ) ); ?>',
                clear: '<?php echo esc_js( __( 'Nothing to do', 'households' ) ); ?>',
                starting: '<?php echo esc_js( __( 'Starting it…', 'households' ) ); ?>',
                failed: '<?php echo esc_js( __( 'That home could not be started. Give it a name.', 'households' ) ); ?>',
            };
            const list = document.querySelector('[data-homes]');
            const form = document.querySelector('[data-start]');

            function render(data) {
                list.innerHTML = '';
                if (!data.homes.length) {
                    list.appendChild(hh.el('li', { class: 'empty', text: t.none }));
                    hh.say('');
                    return;
                }
                data.homes.forEach((home) => {
                    const names = home.here.map((person) => person.name);
                    const count = home.open_tasks === 0 ? t.clear
                        : (home.open_tasks === 1 ? t.task : t.tasks.replace('%d', home.open_tasks));
                    const actions = [hh.el('a', { class: 'button primary', href: hh.homeUrl(home.id), text: t.open })];
                    if (home.can_manage) {
                        actions.push(hh.el('a', { class: 'button', href: hh.homeUrl(home.id, 'manage/'), text: t.manage }));
                    }
                    list.appendChild(hh.el('li', { class: 'row' }, [
                        // Wide enough that the buttons drop to their own line
                        // on a phone rather than sitting beside a short name.
                        hh.el('div', { style: 'flex:1 1 240px' }, [
                            hh.el('h2', { style: 'margin:0 0 2px;font-size:1.15rem' }, [
                                hh.el('a', { href: hh.homeUrl(home.id), text: home.name, style: 'text-decoration:none' }),
                            ]),
                            hh.el('div', { class: 'meta', text: names.length ? t.here + ' ' + names.join(', ') : t.nobody }),
                            hh.el('div', { class: 'meta', text: count }),
                        ]),
                        hh.el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap' }, actions),
                    ]));
                });
                hh.say('');
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                hh.say(t.starting);
                hh.post('start_home', { name: form.name.value })
                    .then((data) => {
                        // A home you have just started is a home with one person
                        // in it, so the next thing anyone does is add the rest.
                        if (data.started) {
                            window.location.href = hh.homeUrl(data.started, 'manage/');
                            return;
                        }
                        render(data);
                        hh.say(t.failed, true);
                    })
                    .catch((error) => hh.say(error.message, true));
            });

            hh.post('get_homes', {})
                .then(render)
                .catch((error) => hh.say(error.message, true));
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
