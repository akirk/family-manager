<?php require __DIR__ . '/_head.php'; ?>
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Your homes', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Things', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Everything kept across the homes you belong to, and which house it is at.', 'households' ); ?></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>

        <section>
            <ul class="plain" data-things></ul>
        </section>
    <script>
        (function() {
            const t = {
                none: '<?php echo esc_js( __( 'Nothing listed in any of your homes yet.', 'households' ) ); ?>',
                moveTo: '<?php echo esc_js( __( 'Move to %s', 'households' ) ); ?>',
                at: '<?php echo esc_js( __( 'at %s', 'households' ) ); ?>',
            };
            const list = document.querySelector('[data-things]');

            function load() {
                hh.say('');
                return hh.post('get_things', {}).then(render).catch((error) => hh.say(error.message, true));
            }

            function move(thing, home) {
                hh.say('');
                // The thing names the home it is leaving, not the page.
                return hh.post('move_note', {
                    home_id: thing.home_id,
                    kind: 'item',
                    note_id: thing.id,
                    target_home_id: home.id,
                }).then(load).catch((error) => hh.say(error.message, true));
            }

            function render(data) {
                list.innerHTML = '';
                if (!data.things.length) {
                    list.appendChild(hh.el('li', { class: 'empty', text: t.none }));
                    hh.say('');
                    return;
                }
                data.things.forEach((thing) => {
                    const buttons = data.homes
                        .filter((home) => home.id !== thing.home_id)
                        .map((home) => hh.el('button', {
                            class: 'quiet', type: 'button', text: t.moveTo.replace('%s', home.name),
                            onclick: () => move(thing, home),
                        }));
                    list.appendChild(hh.el('li', { class: 'row' }, [
                        hh.el('div', {}, [
                            hh.el('strong', { text: thing.title }),
                            hh.el('div', { class: 'meta', text: thing.detail }),
                            hh.el('div', { style: 'margin-top:4px' }, [
                                hh.el('a', {
                                    class: 'pill', href: hh.homeUrl(thing.home_id),
                                    text: t.at.replace('%s', thing.home_name), style: 'text-decoration:none',
                                }),
                            ]),
                        ]),
                        hh.el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap' }, buttons),
                    ]));
                });
                hh.say('');
            }

            load();
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
