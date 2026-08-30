<?php require __DIR__ . '/_head.php'; ?>
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Your homes', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Who is where', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Everyone across the homes you belong to, and where they are today.', 'households' ); ?></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>

        <section>
            <h2><?php echo esc_html__( 'Everyone', 'households' ); ?></h2>
            <ul class="plain" data-everyone></ul>
        </section>

        <section>
            <h2><?php echo esc_html__( 'The next fortnight', 'households' ); ?></h2>
            <p class="meta" data-reading-from></p>
            <p class="meta" data-home-picker></p>
            <p class="meta" data-board-hint hidden><?php echo esc_html__( 'Tap a day to move it. Moving one day leaves the pattern alone.', 'households' ); ?></p>
            <div style="overflow-x:auto">
                <table data-board style="border-collapse:collapse;min-width:100%"></table>
            </div>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Handovers', 'households' ); ?></h2>
            <ul class="plain" data-handovers></ul>
        </section>

        <section data-rotations-section hidden>
            <h2><?php echo esc_html__( 'Rotations', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'A rotation names its homes in order and repeats a cycle of days. It is stored on the person, so every home reads the same answer.', 'households' ); ?></p>
            <div data-rotations style="display:grid;gap:16px"></div>
        </section>
    <script>
        (function() {
            const t = {
                readingFrom: '<?php echo esc_js( __( 'Read from %s.', 'households' ) ); ?>',
                here: '<?php echo esc_js( __( 'here', 'households' ) ); ?>',
                away: '<?php echo esc_js( __( 'away', 'households' ) ); ?>',
                noRotation: '<?php echo esc_js( __( 'does not rotate', 'households' ) ); ?>',
                nobody: '<?php echo esc_js( __( 'Nobody is here today.', 'households' ) ); ?>',
                notTracked: '<?php echo esc_js( __( 'not tracked', 'households' ) ); ?>',
                atHome: '<?php echo esc_js( __( 'at %s', 'households' ) ); ?>',
                child: '<?php echo esc_js( __( 'Child', 'households' ) ); ?>',
                noAccount: '<?php echo esc_js( __( 'No account', 'households' ) ); ?>',
                you: '<?php echo esc_js( __( 'You', 'households' ) ); ?>',
                belongs: '<?php echo esc_js( __( '%d homes', 'households' ) ); ?>',
                noHandovers: '<?php echo esc_js( __( 'No handovers in this window.', 'households' ) ); ?>',
                out: '<?php echo esc_js( __( 'leaving', 'households' ) ); ?>',
                in: '<?php echo esc_js( __( 'arriving', 'households' ) ); ?>',
                elsewhere: '<?php echo esc_js( __( 'elsewhere', 'households' ) ); ?>',
                pattern: '<?php echo esc_js( __( 'Pattern', 'households' ) ); ?>',
                starts: '<?php echo esc_js( __( 'Starts', 'households' ) ); ?>',
                changeover: '<?php echo esc_js( __( 'Changeover', 'households' ) ); ?>',
                homes: '<?php echo esc_js( __( 'Homes, in order', 'households' ) ); ?>',
                save: '<?php echo esc_js( __( 'Save rotation', 'households' ) ); ?>',
                clear: '<?php echo esc_js( __( 'Clear', 'households' ) ); ?>',
                other: '<?php echo esc_js( __( 'Look at another home', 'households' ) ); ?>',
                followPattern: '<?php echo esc_js( __( 'follows the pattern', 'households' ) ); ?>',
            };
            const nodes = {
                readingFrom: document.querySelector('[data-reading-from]'),
                everyone: document.querySelector('[data-everyone]'),
                picker: document.querySelector('[data-home-picker]'),
                board: document.querySelector('[data-board]'),
                boardHint: document.querySelector('[data-board-hint]'),
                handovers: document.querySelector('[data-handovers]'),
                rotations: document.querySelector('[data-rotations]'),
                rotationsSection: document.querySelector('[data-rotations-section]'),
            };
            let state = { start: '', canOrganise: false };

            function load(fields) {
                hh.say('');
                return hh.post('get_whereabouts', Object.assign({ start: state.start }, fields || {}))
                    .then(render)
                    .catch((error) => hh.say(error.message, true));
            }

            function act(action, fields) {
                hh.say('');
                return hh.post(action, Object.assign({ start: state.start }, fields))
                    .then(render)
                    .catch((error) => hh.say(error.message, true));
            }

            function renderEveryone(everyone) {
                nodes.everyone.innerHTML = '';
                everyone.forEach((person) => {
                    const pills = [];
                    if (person.is_you) { pills.push(hh.el('span', { class: 'pill', text: t.you })); }
                    if (person.is_child) { pills.push(hh.el('span', { class: 'pill', text: t.child })); }
                    if (person.label) { pills.push(hh.el('span', { class: 'pill', text: person.label })); }
                    if (!person.user_id) { pills.push(hh.el('span', { class: 'pill warm', text: t.noAccount })); }

                    // Somewhere known, or honestly nowhere: a person who belongs
                    // to several homes and rotates between none could be at any
                    // of them, and guessing would read as an answer.
                    const where = person.location.known
                        ? hh.el('span', { class: 'pill', text: t.atHome.replace('%s', person.location.name) })
                        : hh.el('span', { class: 'pill warm', text: t.notTracked });

                    nodes.everyone.appendChild(hh.el('li', { class: 'row' }, [
                        hh.el('div', {}, [
                            hh.el('a', { href: hh.personUrl(person.id), text: person.name, style: 'font-weight:700' }),
                            hh.el('div', { class: 'meta', text: person.homes.map((home) => home.name).join(' · ') }),
                            hh.el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap;margin-top:4px' }, pills),
                        ]),
                        where,
                    ]));
                });
            }

            function renderBoard(board) {
                nodes.board.innerHTML = '';
                nodes.boardHint.hidden = !state.canOrganise;
                const head = hh.el('tr', {}, [hh.el('th', { style: 'text-align:left;padding:4px 8px' })]);
                board.dates.forEach((date) => {
                    head.appendChild(hh.el('th', {
                        style: 'padding:4px 2px;font-size:0.72rem;font-weight:700;'
                            + (date.is_weekend ? 'color:var(--hh-warm);' : '')
                            + (date.is_today ? 'text-decoration:underline;' : ''),
                        html: date.weekday + '<br>' + date.day,
                    }));
                });
                nodes.board.appendChild(hh.el('thead', {}, [head]));

                const body = hh.el('tbody');
                board.people.forEach((person) => {
                    const row = hh.el('tr', {}, [
                        hh.el('th', { style: 'text-align:left;padding:4px 8px;font-weight:700;white-space:nowrap' }, [
                            hh.el('a', { href: hh.personUrl(person.id), text: person.name, style: 'text-decoration:none' }),
                        ]),
                    ]);
                    board.dates.forEach((date) => {
                        const day = person.days.find((d) => d.date === date.date);
                        const cell = hh.el('td', {
                            title: day ? day.home_name : '',
                            style: 'padding:0;text-align:center;border:1px solid var(--hh-line);'
                                + 'background:' + (!day ? 'transparent' : (day.is_here ? 'color-mix(in srgb, var(--hh-accent) 22%, transparent)' : 'transparent')) + ';'
                                + (day && day.is_override ? 'outline:2px solid var(--hh-warm);outline-offset:-2px;' : ''),
                            text: day ? (day.home_name || '').slice(0, 1) : '·',
                        });
                        if (day && state.canOrganise) {
                            cell.style.cursor = 'pointer';
                            cell.addEventListener('click', () => {
                                // Cycle through this person's homes, then back to
                                // the pattern, which is what a home ID of 0 means.
                                const options = person.homes.map((home) => home.id).concat([0]);
                                const current = day.is_override ? options.indexOf(day.home_id) : options.length - 1;
                                const next = options[(current + 1) % options.length];
                                act('set_override', { person_id: person.id, date: day.date, override_home_id: next });
                            });
                        }
                        row.appendChild(cell);
                    });
                    body.appendChild(row);
                });
                nodes.board.appendChild(body);
            }

            function renderHandovers(board) {
                nodes.handovers.innerHTML = '';
                if (!board.handovers.length) {
                    nodes.handovers.appendChild(hh.el('li', { class: 'empty', text: t.noHandovers }));
                    return;
                }
                board.handovers.forEach((handover) => {
                    const direction = handover.direction === 'out' ? t.out : (handover.direction === 'in' ? t.in : t.elsewhere);
                    nodes.handovers.appendChild(hh.el('li', { class: 'row' }, [
                        hh.el('div', {}, [
                            hh.el('strong', { text: handover.date }),
                            hh.el('div', { class: 'meta', text: handover.people.join(', ') + ' · ' + handover.from_name + ' → ' + handover.to_name }),
                        ]),
                        hh.el('span', { class: 'pill', text: direction }),
                    ]));
                });
            }

            function renderRotations(board) {
                const rotating = board.people.filter((person) => person.can_rotate);
                nodes.rotationsSection.hidden = !state.canOrganise || !rotating.length;
                nodes.rotations.innerHTML = '';
                if (nodes.rotationsSection.hidden) { return; }

                rotating.forEach((person) => {
                    const form = hh.el('form', { class: 'grid' });
                    const patternSelect = hh.el('select', { name: 'pattern' });
                    board.patterns.forEach((pattern) => {
                        const option = hh.el('option', { value: pattern.key, text: pattern.label });
                        if (person.rotation.pattern === pattern.key) { option.selected = true; }
                        patternSelect.appendChild(option);
                    });
                    const start = hh.el('input', { type: 'date', name: 'start_date', value: person.rotation.start_date || '' });
                    const time = hh.el('input', { type: 'time', name: 'changeover_time', value: person.rotation.changeover_time || '17:00' });

                    const homeBoxes = hh.el('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' });
                    person.homes.forEach((home) => {
                        const box = hh.el('input', { type: 'checkbox', value: home.id, style: 'width:auto;min-height:0' });
                        box.checked = (person.rotation.homes || []).indexOf(home.id) !== -1;
                        homeBoxes.appendChild(hh.el('label', { class: 'inline' }, [box, hh.el('span', { text: home.name })]));
                    });

                    form.appendChild(hh.el('div', { class: 'wide' }, [hh.el('strong', { text: person.name })]));
                    form.appendChild(hh.el('label', { text: t.pattern }, [patternSelect]));
                    form.appendChild(hh.el('label', { text: t.starts }, [start]));
                    form.appendChild(hh.el('label', { text: t.changeover }, [time]));
                    form.appendChild(hh.el('div', { class: 'wide' }, [hh.el('div', { class: 'meta', text: t.homes }), homeBoxes]));
                    form.appendChild(hh.el('button', { class: 'primary', type: 'submit', text: t.save }));
                    form.appendChild(hh.el('button', {
                        class: 'quiet', type: 'button', text: t.clear,
                        onclick: () => act('clear_rotation', { person_id: person.id }),
                    }));

                    form.addEventListener('submit', (event) => {
                        event.preventDefault();
                        const homes = Array.from(homeBoxes.querySelectorAll('input:checked')).map((box) => box.value);
                        act('save_rotation', {
                            person_id: person.id,
                            pattern: patternSelect.value,
                            start_date: start.value,
                            changeover_time: time.value,
                            homes: homes,
                        });
                    });

                    nodes.rotations.appendChild(hh.el('div', { style: 'border-top:1px solid var(--hh-line);padding-top:12px' }, [form]));
                });
            }

            function renderPicker(data) {
                nodes.picker.innerHTML = '';
                const others = data.homes.filter((home) => home.id !== data.board.home.id);
                if (!others.length) { return; }
                nodes.picker.appendChild(document.createTextNode(t.other + ' '));
                others.forEach((home) => {
                    const link = hh.el('a', { href: '#', text: home.name, style: 'margin-right:8px' });
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        hh.cfg.homeId = home.id;
                        load();
                    });
                    nodes.picker.appendChild(link);
                });
            }

            function render(data) {
                const board = data.board;
                state.start = board.start;
                state.canOrganise = !!(data.permissions && data.permissions.organise);
                nodes.readingFrom.textContent = t.readingFrom.replace('%s', board.home.name);
                renderEveryone(data.everyone || []);
                renderBoard(board);
                renderHandovers(board);
                renderRotations(board);
                renderPicker(data);
                hh.say('');
            }

            load();
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
