<?php
$hh_home_id = (int) get_query_var( 'id' );
$hh_view_as = (int) get_query_var( 'person_id' );
require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Your homes', 'households' ); ?></a>
        <h1 data-home-name><?php echo esc_html__( 'Home', 'households' ); ?></h1>
        <p class="subtitle" data-here></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>

        <section data-viewing-as hidden>
            <strong data-viewing-as-text></strong>
        </section>

        <section>
            <h2><?php echo esc_html__( 'To do', 'households' ); ?></h2>
            <ul class="plain" data-tasks></ul>
            <form class="grid" data-add-task hidden style="margin-top:12px">
                <label class="wide"><?php echo esc_html__( 'What needs doing', 'households' ); ?>
                    <input type="text" name="title" required>
                </label>
                <label><?php echo esc_html__( 'For whom', 'households' ); ?>
                    <select name="person_id"><option value="0"><?php echo esc_html__( 'Everyone', 'households' ); ?></option></select>
                </label>
                <label><?php echo esc_html__( 'Kind', 'households' ); ?>
                    <select name="task_type">
                        <option value="task"><?php echo esc_html__( 'Task', 'households' ); ?></option>
                        <option value="appointment"><?php echo esc_html__( 'Appointment', 'households' ); ?></option>
                    </select>
                </label>
                <label><?php echo esc_html__( 'When', 'households' ); ?>
                    <input type="date" name="due_date">
                </label>
                <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
            </form>
        </section>

        <section>
            <h2><?php echo esc_html__( 'About this home', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'What the house needs you to know: the wifi, where the water main valve is, which day the bins go out.', 'households' ); ?></p>
            <ul class="plain" data-facts></ul>
            <form class="grid" data-add-note="fact" hidden style="margin-top:12px">
                <label><?php echo esc_html__( 'Label', 'households' ); ?><input type="text" name="title" required></label>
                <label class="wide"><?php echo esc_html__( 'Detail', 'households' ); ?><input type="text" name="detail"></label>
                <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
            </form>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Things kept here', 'households' ); ?></h2>
            <ul class="plain" data-items></ul>
            <form class="grid" data-add-note="item" hidden style="margin-top:12px">
                <label><?php echo esc_html__( 'Thing', 'households' ); ?><input type="text" name="title" required></label>
                <label class="wide"><?php echo esc_html__( 'Where it lives', 'households' ); ?><input type="text" name="detail"></label>
                <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
            </form>
        </section>

        <section data-birthdays-section hidden>
            <h2><?php echo esc_html__( 'Birthdays coming up', 'households' ); ?></h2>
            <ul class="plain" data-birthdays></ul>
        </section>
    <script>
        (function() {
            const viewAs = <?php echo (int) $hh_view_as; ?>;
            const t = {
                here: '<?php echo esc_js( __( 'Here today:', 'households' ) ); ?>',
                nobody: '<?php echo esc_js( __( 'Nobody here today.', 'households' ) ); ?>',
                noTasks: '<?php echo esc_js( __( 'Nothing to do.', 'households' ) ); ?>',
                noFacts: '<?php echo esc_js( __( 'Nothing written down yet.', 'households' ) ); ?>',
                noItems: '<?php echo esc_js( __( 'Nothing listed yet.', 'households' ) ); ?>',
                everyone: '<?php echo esc_js( __( 'Everyone', 'households' ) ); ?>',
                remove: '<?php echo esc_js( __( 'Remove', 'households' ) ); ?>',
                appointment: '<?php echo esc_js( __( 'Appointment', 'households' ) ); ?>',
                viewingAs: '<?php echo esc_js( __( 'You are looking at this home as %s sees it.', 'households' ) ); ?>',
                turning: '<?php echo esc_js( __( '%1$s turns %2$d in %3$d days', 'households' ) ); ?>',
            };

            const nodes = {
                name: document.querySelector('[data-home-name]'),
                here: document.querySelector('[data-here]'),
                tasks: document.querySelector('[data-tasks]'),
                facts: document.querySelector('[data-facts]'),
                items: document.querySelector('[data-items]'),
                birthdays: document.querySelector('[data-birthdays]'),
                birthdaysSection: document.querySelector('[data-birthdays-section]'),
                viewingAs: document.querySelector('[data-viewing-as]'),
                viewingAsText: document.querySelector('[data-viewing-as-text]'),
                addTask: document.querySelector('[data-add-task]'),
            };

            function send(action, fields) {
                hh.say('');
                return hh.post(action, Object.assign({ view_as: viewAs || '' }, fields))
                    .then(render)
                    .catch((error) => hh.say(error.message, true));
            }

            function renderTasks(data) {
                nodes.tasks.innerHTML = '';
                if (!data.tasks.length) {
                    nodes.tasks.appendChild(hh.el('li', { class: 'empty', text: t.noTasks }));
                    return;
                }
                data.tasks.forEach((task) => {
                    const box = hh.el('input', { type: 'checkbox', style: 'width:auto;min-height:0' });
                    box.checked = task.is_done;
                    box.addEventListener('change', () => send('toggle_task', { task_id: task.id }));
                    const bits = [task.person ? task.person : t.everyone];
                    if (task.due_date) { bits.push(task.due_date); }
                    if (task.task_type === 'appointment') { bits.push(t.appointment); }
                    const right = [];
                    if (data.viewer.can_organise) {
                        right.push(hh.el('button', {
                            class: 'quiet', type: 'button', text: t.remove,
                            onclick: () => send('remove_task', { task_id: task.id }),
                        }));
                    }
                    nodes.tasks.appendChild(hh.el('li', { class: 'row' }, [
                        hh.el('label', { class: 'inline' }, [
                            box,
                            hh.el('span', { class: task.is_done ? 'done' : '' }, [
                                hh.el('span', { text: task.title }),
                                hh.el('span', { class: 'meta', text: ' · ' + bits.join(' · ') }),
                            ]),
                        ]),
                        hh.el('div', {}, right),
                    ]));
                });
            }

            function renderNotes(target, notes, kind, empty, canOrganise) {
                target.innerHTML = '';
                if (!notes.length) {
                    target.appendChild(hh.el('li', { class: 'empty', text: empty }));
                    return;
                }
                notes.forEach((note) => {
                    const right = [];
                    if (canOrganise) {
                        right.push(hh.el('button', {
                            class: 'quiet', type: 'button', text: t.remove,
                            onclick: () => send('remove_note', { kind: kind, note_id: note.id }),
                        }));
                    }
                    target.appendChild(hh.el('li', { class: 'row' }, [
                        hh.el('div', {}, [
                            hh.el('strong', { text: note.title }),
                            hh.el('div', { class: 'meta', text: note.detail }),
                        ]),
                        hh.el('div', {}, right),
                    ]));
                });
            }

            function render(data) {
                nodes.name.textContent = data.home.name;
                const names = data.here.map((p) => p.name);
                nodes.here.textContent = names.length ? t.here + ' ' + names.join(', ') : t.nobody;

                nodes.viewingAs.hidden = !data.viewer.viewing_as;
                if (data.viewer.viewing_as) {
                    nodes.viewingAsText.textContent = t.viewingAs.replace('%s', data.subject.name);
                }

                renderTasks(data);
                renderNotes(nodes.facts, data.facts, 'fact', t.noFacts, data.viewer.can_organise);
                renderNotes(nodes.items, data.items, 'item', t.noItems, data.viewer.can_organise);

                nodes.birthdaysSection.hidden = !data.birthdays.length;
                nodes.birthdays.innerHTML = '';
                data.birthdays.slice(0, 5).forEach((birthday) => {
                    nodes.birthdays.appendChild(hh.el('li', {
                        text: t.turning.replace('%1$s', birthday.name).replace('%2$d', birthday.turning).replace('%3$d', birthday.days_until),
                    }));
                });

                document.querySelectorAll('[data-add-task], [data-add-note]').forEach((form) => {
                    form.hidden = !data.viewer.can_organise || data.viewer.viewing_as;
                });
                const select = nodes.addTask.querySelector('[name="person_id"]');
                select.innerHTML = '';
                select.appendChild(hh.el('option', { value: '0', text: t.everyone }));
                data.people.forEach((person) => select.appendChild(hh.el('option', { value: person.id, text: person.name })));

                hh.say('');
            }

            nodes.addTask.addEventListener('submit', (event) => {
                event.preventDefault();
                const form = event.target;
                send('add_task', {
                    title: form.title.value,
                    person_id: form.person_id.value,
                    task_type: form.task_type.value,
                    due_date: form.due_date.value,
                }).then(() => form.reset());
            });

            document.querySelectorAll('[data-add-note]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    send('add_note', {
                        kind: form.getAttribute('data-add-note'),
                        title: form.title.value,
                        detail: form.detail.value,
                    }).then(() => form.reset());
                });
            });

            send('get', {});
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
