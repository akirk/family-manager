<?php require __DIR__ . '/_head.php'; ?>
        <h1><?php echo esc_html__( 'Your homes', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Every home you belong to, and who is under each roof today.', 'households' ); ?></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>
        <ul class="plain" data-homes></ul>
        <p class="subtitle"><a href="<?php echo esc_url( home_url( '/households/where/' ) ); ?>"><?php echo esc_html__( 'Who is where, day by day', 'households' ); ?></a></p>
    <script>
        (function() {
            const list = document.querySelector('[data-homes]');
            const t = {
                here: '<?php echo esc_js( __( 'Here today:', 'households' ) ); ?>',
                nobody: '<?php echo esc_js( __( 'Nobody here today.', 'households' ) ); ?>',
                open: '<?php echo esc_js( __( 'Open', 'households' ) ); ?>',
                manage: '<?php echo esc_js( __( 'Manage', 'households' ) ); ?>',
                none: '<?php echo esc_js( __( 'You do not belong to a home yet.', 'households' ) ); ?>',
                task: '<?php echo esc_js( __( '1 thing to do', 'households' ) ); ?>',
                tasks: '<?php echo esc_js( __( '%d things to do', 'households' ) ); ?>',
                clear: '<?php echo esc_js( __( 'Nothing to do', 'households' ) ); ?>',
            };

            function render(homes) {
                list.innerHTML = '';
                if (!homes.length) {
                    list.appendChild(hh.el('li', { class: 'empty', text: t.none }));
                    return;
                }
                homes.forEach((home) => {
                    const names = home.here.map((p) => p.name);
                    const count = home.open_tasks === 0 ? t.clear
                        : (home.open_tasks === 1 ? t.task : t.tasks.replace('%d', home.open_tasks));
                    const actions = [hh.el('a', { class: 'button primary', href: hh.homeUrl(home.id), text: t.open })];
                    if (home.can_manage) {
                        actions.push(hh.el('a', { class: 'button', href: hh.homeUrl(home.id, 'manage/'), text: t.manage }));
                    }
                    list.appendChild(hh.el('li', { class: 'row' }, [
                        hh.el('div', {}, [
                            hh.el('h2', { style: 'margin:0 0 2px;font-size:1.15rem' }, [
                                hh.el('a', { href: hh.homeUrl(home.id), text: home.name, style: 'text-decoration:none' })
                            ]),
                            hh.el('div', { class: 'meta', text: names.length ? t.here + ' ' + names.join(', ') : t.nobody }),
                            hh.el('div', { class: 'meta', text: count }),
                        ]),
                        hh.el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap' }, actions),
                    ]));
                });
            }

            hh.post('get_homes', {})
                .then((data) => { render(data.homes); hh.say(''); })
                .catch((error) => hh.say(error.message, true));
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
