<?php
$hh_home_id = (int) get_query_var( 'id' );
require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Your homes', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Manage this home', 'households' ); ?></h1>
        <p class="subtitle" data-home-name></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>

        <section>
            <h2><?php echo esc_html__( 'Name', 'households' ); ?></h2>
            <form class="grid" data-rename>
                <label class="wide"><?php echo esc_html__( 'What this home is called', 'households' ); ?>
                    <input type="text" name="name" required>
                </label>
                <button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button>
            </form>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Who is in it', 'households' ); ?></h2>
            <ul class="plain" data-people></ul>
        </section>

        <section>
            <h2><?php echo esc_html__( 'Add someone', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'An email address gives them an account they can log in with. Leave it empty for someone who will never log in — a small child, or a relative you are only keeping notes about.', 'households' ); ?></p>
            <form class="grid" data-add-person>
                <label><?php echo esc_html__( 'Name', 'households' ); ?><input type="text" name="name" required></label>
                <label><?php echo esc_html__( 'Email (optional)', 'households' ); ?><input type="email" name="email"></label>
                <label><?php echo esc_html__( 'Password (optional)', 'households' ); ?><input type="text" name="password" autocomplete="off"></label>
                <label><?php echo esc_html__( 'Called', 'households' ); ?>
                    <input type="text" name="label" placeholder="<?php echo esc_attr__( 'Grandparent', 'households' ); ?>">
                </label>
                <label><?php echo esc_html__( 'Born', 'households' ); ?><input type="date" name="birthdate"></label>
                <label class="inline"><input type="checkbox" name="is_child"> <?php echo esc_html__( 'Is a child', 'households' ); ?></label>
                <button class="primary" type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
            </form>
        </section>
    <script>
        (function() {
            const t = {
                child: '<?php echo esc_js( __( 'Child', 'households' ) ); ?>',
                isChild: '<?php echo esc_js( __( 'Is a child', 'households' ) ); ?>',
                admin: '<?php echo esc_js( __( 'Administers this home', 'households' ) ); ?>',
                noAccount: '<?php echo esc_js( __( 'No account', 'households' ) ); ?>',
                makeAdmin: '<?php echo esc_js( __( 'Make administrator', 'households' ) ); ?>',
                dropAdmin: '<?php echo esc_js( __( 'Remove as administrator', 'households' ) ); ?>',
                remove: '<?php echo esc_js( __( 'Remove from this home', 'households' ) ); ?>',
                confirm: '<?php echo esc_js( __( 'Remove %s from this home? Their record and everything written on it stays.', 'households' ) ); ?>',
                page: '<?php echo esc_js( __( 'Open page', 'households' ) ); ?>',
                saved: '<?php echo esc_js( __( 'Saved.', 'households' ) ); ?>',
                none: '<?php echo esc_js( __( 'Nobody here yet.', 'households' ) ); ?>',
                you: '<?php echo esc_js( __( 'You', 'households' ) ); ?>',
            };
            const nodes = {
                name: document.querySelector('[data-home-name]'),
                people: document.querySelector('[data-people]'),
                rename: document.querySelector('[data-rename]'),
                addPerson: document.querySelector('[data-add-person]'),
            };

            function send(action, fields) {
                hh.say('');
                return hh.post(action, fields).then(render).catch((error) => hh.say(error.message, true));
            }

            function render(data) {
                nodes.name.textContent = data.home.name;
                nodes.rename.name.value = data.home.name;

                nodes.people.innerHTML = '';
                if (!data.people.length) {
                    nodes.people.appendChild(hh.el('li', { class: 'empty', text: t.none }));
                }
                data.people.forEach((person) => {
                    const isSelf = person.id === data.viewer.person_id;
                    const isAdmin = data.admins.indexOf(person.id) !== -1;
                    const pills = [];
                    if (person.is_child) { pills.push(hh.el('span', { class: 'pill', text: t.child })); }
                    if (person.label) { pills.push(hh.el('span', { class: 'pill', text: person.label })); }
                    if (isAdmin) { pills.push(hh.el('span', { class: 'pill', text: t.admin })); }
                    if (!person.user_id) { pills.push(hh.el('span', { class: 'pill warm', text: t.noAccount })); }

                    const actions = [hh.el('a', { class: 'button quiet', href: hh.personUrl(person.id), text: t.page })];
                    // Someone with no account has nothing to administer with, and
                    // an administrator may not drop themselves and leave nobody.
                    if (person.user_id && !isSelf) {
                        actions.push(hh.el('button', {
                            class: 'quiet', type: 'button', text: isAdmin ? t.dropAdmin : t.makeAdmin,
                            onclick: () => send('set_admin', { person_id: person.id, is_admin: isAdmin ? 0 : 1 }),
                        }));
                    }
                    if (!isSelf) {
                        actions.push(hh.el('button', {
                            class: 'quiet', type: 'button', text: t.remove,
                            onclick: () => {
                                if (window.confirm(t.confirm.replace('%s', person.name))) {
                                    send('remove_person', { person_id: person.id });
                                }
                            },
                        }));
                    }

                    const childBox = hh.el('input', { type: 'checkbox', style: 'width:auto;min-height:0' });
                    childBox.checked = person.is_child;
                    childBox.addEventListener('change', () => send('update_person', {
                        person_id: person.id, is_child: childBox.checked ? 1 : 0, label: person.label,
                    }));

                    nodes.people.appendChild(hh.el('li', { class: 'row' }, [
                        hh.el('div', {}, [
                            hh.el('strong', { text: person.name + (isSelf ? ' (' + t.you + ')' : '') }),
                            hh.el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap;margin:4px 0' }, pills),
                            hh.el('label', { class: 'inline' }, [childBox, hh.el('span', { class: 'meta', text: t.isChild })]),
                        ]),
                        hh.el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start' }, actions),
                    ]));
                });
                hh.say('');
            }

            nodes.rename.addEventListener('submit', (event) => {
                event.preventDefault();
                send('update_home', { name: nodes.rename.name.value }).then(() => hh.say(t.saved));
            });

            nodes.addPerson.addEventListener('submit', (event) => {
                event.preventDefault();
                const form = event.target;
                send('add_person', {
                    name: form.name.value,
                    email: form.email.value,
                    password: form.password.value,
                    label: form.label.value,
                    birthdate: form.birthdate.value,
                    is_child: form.is_child.checked ? 1 : 0,
                }).then(() => form.reset());
            });

            send('get', {});
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
