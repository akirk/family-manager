<?php
/**
 * The index is your day, not a directory of houses: where you are, what is
 * asked of you wherever it was written down, and the fortnight ahead across
 * every home you belong to.
 */
require __DIR__ . '/_head.php';
?>
        <h1><?php echo esc_html__( 'Your day', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Where you are, what is yours to do, and what is coming across your homes.', 'households' ); ?></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>

        <section data-today-section hidden>
            <h2><?php echo esc_html__( 'Today', 'households' ); ?></h2>
            <p data-where style="margin:0"></p>
            <div data-say hidden style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px"></div>
        </section>

        <section data-yours-section hidden>
            <h2><?php echo esc_html__( 'Yours to do', 'households' ); ?></h2>
            <ul class="plain" data-yours></ul>
        </section>

        <section data-shared-section hidden>
            <h2><?php echo esc_html__( 'Nobody’s yet', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'Written down for the house rather than for a person. Ticking one is claiming it.', 'households' ); ?></p>
            <ul class="plain" data-shared></ul>
        </section>

        <section data-agenda-section hidden>
            <h2><?php echo esc_html__( 'The fortnight ahead', 'households' ); ?></h2>
            <ul class="plain" data-agenda></ul>
        </section>

        <p class="subtitle">
            <a href="<?php echo esc_url( home_url( '/households/homes/' ) ); ?>"><?php echo esc_html__( 'Your homes, and starting another', 'households' ); ?></a>
            ·
            <a href="<?php echo esc_url( home_url( '/households/where/' ) ); ?>"><?php echo esc_html__( 'Who is where, day by day', 'households' ); ?></a>
            ·
            <a href="<?php echo esc_url( home_url( '/households/things/' ) ); ?>"><?php echo esc_html__( 'Everything kept across your homes', 'households' ); ?></a>
        </p>
    <script>
        (function() {
            const t = {
                atHome: '<?php echo esc_js( __( 'You are at %s today.', 'households' ) ); ?>',
                untilThen: '<?php echo esc_js( __( 'Until %1$s, then %2$s.', 'households' ) ); ?>',
                withYou: '<?php echo esc_js( __( 'Here with you: %s.', 'households' ) ); ?>',
                alone: '<?php echo esc_js( __( 'Nobody else is here today.', 'households' ) ); ?>',
                askWhere: '<?php echo esc_js( __( 'Where are you today?', 'households' ) ); ?>',
                justToday: '<?php echo esc_js( __( 'Nothing says where you are, so nothing is assumed. Answering is about today alone; it sets up no arrangement.', 'households' ) ); ?>',
                unsayPattern: '<?php echo esc_js( __( 'Back to the pattern', 'households' ) ); ?>',
                unsay: '<?php echo esc_js( __( 'Not sure', 'households' ) ); ?>',
                nowhere: '<?php echo esc_js( __( 'You do not belong to a home yet.', 'households' ) ); ?>',
                startOne: '<?php echo esc_js( __( 'Start one', 'households' ) ); ?>',
                noTasks: '<?php echo esc_js( __( 'Nothing is asked of you right now.', 'households' ) ); ?>',
                overdue: '<?php echo esc_js( __( 'overdue', 'households' ) ); ?>',
                appointment: '<?php echo esc_js( __( 'appointment', 'households' ) ); ?>',
                at: '<?php echo esc_js( __( 'at %s', 'households' ) ); ?>',
                quiet: '<?php echo esc_js( __( 'Nothing due, nobody moving, no birthdays in the next fortnight.', 'households' ) ); ?>',
                birthday: '<?php echo esc_js( __( '%1$s turns %2$d', 'households' ) ); ?>',
                move: '<?php echo esc_js( __( '%1$s: %2$s to %3$s', 'households' ) ); ?>',
                forWhom: '<?php echo esc_js( __( 'for %s', 'households' ) ); ?>',
                everyone: '<?php echo esc_js( __( 'for the house', 'households' ) ); ?>',
            };
            const nodes = {
                where: document.querySelector('[data-where]'),
                say: document.querySelector('[data-say]'),
                yours: document.querySelector('[data-yours]'),
                shared: document.querySelector('[data-shared]'),
                agenda: document.querySelector('[data-agenda]'),
            };

            function section(name, visible) {
                document.querySelector('[data-' + name + '-section]').hidden = !visible;
            }

            function homePill(id, name) {
                return hh.el('a', {
                    class: 'pill', href: hh.homeUrl(id),
                    text: t.at.replace('%s', name), style: 'text-decoration:none',
                });
            }

            /** Say where you are today, or take the answer back with a 0. */
            function say(homeId) {
                hh.say('');
                return hh.post('say_where', { said_home_id: homeId })
                    .then(render).catch((error) => hh.say(error.message, true));
            }

            /**
             * Where you are, when that stops being true, and — because the app
             * would rather ask than guess — the homes you could say instead.
             */
            function renderWhere(where, homes) {
                nodes.where.innerHTML = '';
                if (where.known) {
                    // The home is a link inside the sentence, so the sentence is
                    // split around its placeholder rather than pasted together.
                    const parts = t.atHome.split('%s');
                    nodes.where.appendChild(document.createTextNode(parts[0]));
                    nodes.where.appendChild(hh.el('a', { href: hh.homeUrl(where.home_id), text: where.name }));
                    nodes.where.appendChild(document.createTextNode(parts[1] || ''));
                    if (where.until && where.next_name) {
                        nodes.where.appendChild(document.createTextNode(' '));
                        nodes.where.appendChild(hh.el('span', {
                            class: 'meta',
                            text: t.untilThen.replace('%1$s', where.until_label).replace('%2$s', where.next_name),
                        }));
                    }
                    nodes.where.appendChild(hh.el('div', {
                        class: 'meta',
                        text: where.with_you.length ? t.withYou.replace('%s', where.with_you.join(', ')) : t.alone,
                    }));
                } else {
                    nodes.where.appendChild(hh.el('strong', { text: t.askWhere }));
                    nodes.where.appendChild(hh.el('div', { class: 'meta', text: t.justToday }));
                }

                // Belong to one home and there is nothing to ask. Belong to
                // more and the answer is a button per home, the one you are at
                // already pressed.
                nodes.say.innerHTML = '';
                nodes.say.hidden = homes.length < 2;
                if (nodes.say.hidden) {
                    return;
                }
                homes.forEach((home) => {
                    const current = where.home_id === home.id;
                    nodes.say.appendChild(hh.el('button', {
                        type: 'button', text: home.name,
                        class: current ? 'primary' : '',
                        'aria-pressed': current ? 'true' : 'false',
                        onclick: () => say(home.id),
                    }));
                });
                if (where.said) {
                    nodes.say.appendChild(hh.el('button', {
                        type: 'button', class: 'quiet',
                        text: where.rotates ? t.unsayPattern : t.unsay,
                        onclick: () => say(0),
                    }));
                }
            }

            /** A task, tickable from here: the home it lives in comes with it. */
            function taskItem(task, reload) {
                const box = hh.el('input', { type: 'checkbox', style: 'width:auto;min-height:0' });
                box.addEventListener('change', () => {
                    hh.say('');
                    // The task names its own home; this page is about none of them.
                    hh.post('toggle_task', { home_id: task.home_id, task_id: task.id })
                        .then(reload).catch((error) => hh.say(error.message, true));
                });
                const bits = [];
                if (task.due_label) { bits.push(task.due_label); }
                if (task.task_type === 'appointment') { bits.push(t.appointment); }
                const meta = bits.length ? hh.el('span', { class: 'meta', text: ' · ' + bits.join(' · ') }) : null;
                return hh.el('li', { class: 'row' }, [
                    hh.el('label', { class: 'inline' }, [
                        box,
                        hh.el('span', {}, [hh.el('span', { text: task.title }), meta]),
                    ]),
                    hh.el('div', { style: 'display:flex;gap:6px;align-items:center' }, [
                        task.is_overdue ? hh.el('span', { class: 'pill warm', text: t.overdue }) : null,
                        homePill(task.home_id, task.home_name),
                    ]),
                ]);
            }

            function renderTasks(target, tasks, reload) {
                target.innerHTML = '';
                tasks.forEach((task) => target.appendChild(taskItem(task, reload)));
            }

            /** One dated line, whatever kind of thing it is. */
            function agendaItem(entry) {
                let title = entry.title;
                const bits = [];
                if (entry.kind === 'birthday') {
                    title = t.birthday.replace('%1$s', entry.title).replace('%2$d', entry.turning);
                } else if (entry.kind === 'move') {
                    title = t.move
                        .replace('%1$s', entry.people.join(', '))
                        .replace('%2$s', entry.from_name)
                        .replace('%3$s', entry.home_name);
                } else {
                    bits.push(entry.who ? t.forWhom.replace('%s', entry.who) : t.everyone);
                    if (entry.kind === 'appointment') { bits.push(t.appointment); }
                }
                return hh.el('li', { class: 'row' }, [
                    // Wide enough that the pills drop to their own line on a
                    // phone rather than splitting the entry mid-sentence.
                    hh.el('div', { style: 'flex:1 1 240px' }, [
                        hh.el('strong', { text: title }),
                        bits.length ? hh.el('div', { class: 'meta', text: bits.join(' · ') }) : null,
                    ]),
                    hh.el('div', { style: 'display:flex;gap:6px;align-items:center;flex-wrap:wrap' }, [
                        entry.home_id && entry.kind !== 'move' ? homePill(entry.home_id, entry.home_name) : null,
                        hh.el('span', { class: 'pill', text: entry.when }),
                    ]),
                ]);
            }

            function render(data) {
                // Belonging to no home is the one thing this page cannot be
                // about; it says so, and points at where a home is started.
                if (!data.homes.length) {
                    section('today', true);
                    nodes.where.innerHTML = '';
                    nodes.where.appendChild(document.createTextNode(t.nowhere + ' '));
                    nodes.where.appendChild(hh.el('a', { href: hh.cfg.baseUrl + 'homes/', text: t.startOne }));
                    nodes.say.hidden = true;
                    ['yours', 'shared', 'agenda'].forEach((name) => section(name, false));
                    hh.say('');
                    return;
                }

                section('today', true);
                renderWhere(data.where, data.homes);

                // An empty list of your own is worth saying; an empty list of
                // the house's is just a section nobody needs to see.
                section('yours', true);
                nodes.yours.innerHTML = '';
                if (data.yours.length) {
                    renderTasks(nodes.yours, data.yours, load);
                } else {
                    nodes.yours.appendChild(hh.el('li', { class: 'empty', text: t.noTasks }));
                }

                section('shared', data.shared.length > 0);
                renderTasks(nodes.shared, data.shared, load);

                section('agenda', true);
                nodes.agenda.innerHTML = '';
                if (data.agenda.length) {
                    data.agenda.forEach((entry) => nodes.agenda.appendChild(agendaItem(entry)));
                } else {
                    nodes.agenda.appendChild(hh.el('li', { class: 'empty', text: t.quiet }));
                }

                hh.say('');
            }

            function load() {
                return hh.post('get_my_day', {})
                    .then(render)
                    .catch((error) => hh.say(error.message, true));
            }

            load();
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
