<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_title(); ?></title>
    <?php wp_app_head(); ?>
    <style>
        :root {
            color-scheme: light dark;
            --fm-bg: #f6f7f2;
            --fm-text: #17201b;
            --fm-muted: #607067;
            --fm-surface: #ffffff;
            --fm-line: #d9e0d8;
            --fm-accent: #176b5b;
            --fm-accent-strong: #0f4f43;
            --fm-warm: #9b5d2f;
            --fm-blue: #315f8f;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --fm-bg: #111614;
                --fm-text: #eef3ee;
                --fm-muted: #a7b3aa;
                --fm-surface: #1a211e;
                --fm-line: #344039;
                --fm-accent: #55b7a2;
                --fm-accent-strong: #87d4c2;
                --fm-warm: #d29561;
                --fm-blue: #86add8;
            }
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: var(--fm-bg); color: var(--fm-text); line-height: 1.5; }
        button, input, select { font: inherit; }
        button { min-height: 40px; border: 1px solid var(--fm-accent-strong); border-radius: 6px; background: var(--fm-accent); color: #fff; font-weight: 700; cursor: pointer; }
        button.secondary { background: transparent; color: var(--fm-accent-strong); }
        input, select { width: 100%; min-height: 40px; padding: 0 10px; border: 1px solid var(--fm-line); border-radius: 6px; background: var(--fm-surface); color: var(--fm-text); }
        main { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 40px; }
        .topbar { display: flex; justify-content: space-between; gap: 16px; align-items: end; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(2rem, 5vw, 4rem); line-height: 1; letter-spacing: 0; }
        .subtitle { max-width: 720px; margin: 8px 0 0; color: var(--fm-muted); font-size: 1.05rem; }
        .status { color: var(--fm-muted); font-size: 0.92rem; min-height: 22px; }
        .grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(320px, 0.9fr); gap: 16px; align-items: start; }
        .panel { background: var(--fm-surface); border: 1px solid var(--fm-line); border-radius: 8px; padding: 16px; }
        .panel h2 { margin: 0 0 12px; font-size: 1.05rem; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
        .stat { border-left: 4px solid var(--fm-accent); background: var(--fm-surface); border-radius: 6px; padding: 12px; }
        .stat:nth-child(2) { border-color: var(--fm-blue); }
        .stat:nth-child(3) { border-color: var(--fm-warm); }
        .stat strong { display: block; font-size: 1.6rem; line-height: 1; }
        .stat span { color: var(--fm-muted); font-size: 0.85rem; }
        .forms { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        form { display: grid; gap: 8px; }
        .row { display: grid; grid-template-columns: 1fr 92px; gap: 8px; }
        .task-list, .member-list, .reward-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
        .item { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center; border: 1px solid var(--fm-line); border-radius: 6px; padding: 10px; }
        .item.done { opacity: 0.62; }
        .item.done .title { text-decoration: line-through; }
        .title { font-weight: 750; }
        .meta { color: var(--fm-muted); font-size: 0.86rem; }
        .pill { display: inline-flex; align-items: center; min-height: 26px; padding: 0 8px; border-radius: 999px; background: color-mix(in srgb, var(--fm-accent) 12%, transparent); color: var(--fm-accent-strong); font-size: 0.82rem; font-weight: 700; white-space: nowrap; }
        .empty { color: var(--fm-muted); border: 1px dashed var(--fm-line); border-radius: 6px; padding: 18px; text-align: center; }

        .viewing-as { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 16px; padding: 10px 14px; border-radius: 6px; background: color-mix(in srgb, var(--fm-warm) 14%, transparent); border: 1px solid var(--fm-warm); }
        .viewing-as a { color: var(--fm-warm); font-weight: 700; }
        .member-link { color: inherit; text-decoration: none; }
        .member-link:hover .title { text-decoration: underline; }
        .member-actions { display: flex; gap: 6px; align-items: center; }
        .member-actions select { min-height: 32px; width: auto; }
        .member-actions button { min-height: 32px; padding: 0 8px; }
        [hidden] { display: none !important; }
        @media (max-width: 860px) {
            .topbar, .grid, .forms { grid-template-columns: 1fr; display: grid; }
            .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main id="family-manager-app">
        <div class="topbar">
            <div>
                <h1><?php echo esc_html__( 'Family Manager', 'family-manager' ); ?></h1>
                <p class="subtitle"><?php echo esc_html__( 'A household dashboard for tasks, appointments and rewards. Every member has their own login and their own view.', 'family-manager' ); ?></p>
            </div>
            <div class="status" data-status><?php echo esc_html__( 'Loading household...', 'family-manager' ); ?></div>
        </div>

        <div class="viewing-as" data-viewing-as hidden>
            <span><?php echo esc_html__( 'Viewing as', 'family-manager' ); ?> <strong data-viewing-name></strong></span>
            <a href="<?php echo esc_url( home_url( '/family-manager/' ) ); ?>"><?php echo esc_html__( 'Back to my view', 'family-manager' ); ?></a>
        </div>
        <section class="stats" aria-label="<?php echo esc_attr__( 'Household summary', 'family-manager' ); ?>">
            <div class="stat"><strong data-stat="tasks">0</strong><span><?php echo esc_html__( 'Open tasks', 'family-manager' ); ?></span></div>
            <div class="stat"><strong data-stat="appointments">0</strong><span><?php echo esc_html__( 'Appointments', 'family-manager' ); ?></span></div>
            <div class="stat"><strong data-stat="points">0</strong><span><?php echo esc_html__( 'Reward points', 'family-manager' ); ?></span></div>
        </section>

        <section class="forms" data-organiser>
            <div class="panel">
                <h2><?php echo esc_html__( 'Add Member', 'family-manager' ); ?></h2>
                <form data-action="add_member">
                    <input name="name" required placeholder="<?php echo esc_attr__( 'Name', 'family-manager' ); ?>">
                    <select name="role" data-roles></select>
                    <input name="email" type="email" placeholder="<?php echo esc_attr__( 'Email (optional, links an existing account)', 'family-manager' ); ?>">
                    <input name="password" type="text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Password (optional)', 'family-manager' ); ?>">
                    <button type="submit"><?php echo esc_html__( 'Create account', 'family-manager' ); ?></button>
                </form>
            </div>
            <div class="panel">
                <h2><?php echo esc_html__( 'Add Task', 'family-manager' ); ?></h2>
                <form data-action="add_task">
                    <input name="title" required placeholder="<?php echo esc_attr__( 'Task or appointment', 'family-manager' ); ?>">
                    <select name="member_id" data-members><option value="0"><?php echo esc_html__( 'Whole household', 'family-manager' ); ?></option></select>
                    <div class="row">
                        <select name="task_type">
                            <option value="task"><?php echo esc_html__( 'Task', 'family-manager' ); ?></option>
                            <option value="appointment"><?php echo esc_html__( 'Appointment', 'family-manager' ); ?></option>
                        </select>
                        <input name="points" type="number" min="0" step="1" value="5" aria-label="<?php echo esc_attr__( 'Points', 'family-manager' ); ?>">
                    </div>
                    <input name="due_date" type="date" aria-label="<?php echo esc_attr__( 'Due date', 'family-manager' ); ?>">
                    <button type="submit"><?php echo esc_html__( 'Add task', 'family-manager' ); ?></button>
                </form>
            </div>
            <div class="panel">
                <h2><?php echo esc_html__( 'Add Reward', 'family-manager' ); ?></h2>
                <form data-action="add_reward">
                    <input name="title" required placeholder="<?php echo esc_attr__( 'Reward', 'family-manager' ); ?>">
                    <select name="member_id" data-members><option value="0"><?php echo esc_html__( 'Any member', 'family-manager' ); ?></option></select>
                    <input name="points" type="number" min="0" step="1" value="20" aria-label="<?php echo esc_attr__( 'Points', 'family-manager' ); ?>">
                    <button type="submit"><?php echo esc_html__( 'Add reward', 'family-manager' ); ?></button>
                </form>
            </div>
        </section>

        <section class="grid">
            <div class="panel">
                <h2><?php echo esc_html__( 'Today and Next Up', 'family-manager' ); ?></h2>
                <ul class="task-list" data-tasks></ul>
            </div>
            <div class="panel">
                <h2><?php echo esc_html__( 'Members', 'family-manager' ); ?></h2>
                <ul class="member-list" data-members-list></ul>
                <h2 style="margin-top:18px;"><?php echo esc_html__( 'Reward Ideas', 'family-manager' ); ?></h2>
                <ul class="reward-list" data-rewards></ul>
            </div>
        </section>
    </main>

    <script>
        window.familyManager = <?php echo wp_json_encode( [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'family_manager_app' ),
            'viewAs'  => (int) get_query_var( 'id' ),
            'memberUrl' => home_url( '/family-manager/member/' ),
        ] ); ?>;
    </script>
    <script>
        (function() {
            const app = document.getElementById('family-manager-app');
            const status = app.querySelector('[data-status]');
            const taskList = app.querySelector('[data-tasks]');
            const memberList = app.querySelector('[data-members-list]');
            const rewardList = app.querySelector('[data-rewards]');
            const memberSelects = app.querySelectorAll('[data-members]');
            const stats = {
                tasks: app.querySelector('[data-stat="tasks"]'),
                appointments: app.querySelector('[data-stat="appointments"]'),
                points: app.querySelector('[data-stat="points"]')
            };

            function request(payload) {
                const body = new FormData();
                body.append('action', 'family_manager_dashboard');
                body.append('nonce', window.familyManager.nonce);
                if (window.familyManager.viewAs) {
                    body.append('view_as', window.familyManager.viewAs);
                }
                Object.keys(payload || {}).forEach((key) => body.append(key, payload[key]));

                return fetch(window.familyManager.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body
                }).then((response) => response.json());
            }

            const viewingAs = app.querySelector('[data-viewing-as]');
            const organiser = app.querySelector('[data-organiser]');
            const roleSelects = app.querySelectorAll('[data-roles]');
            const roleLabels = {};

            function render(data) {
                Object.assign(roleLabels, data.roles);
                viewingAs.hidden = !data.permissions.viewing_as_other;
                viewingAs.querySelector('[data-viewing-name]').textContent = data.subject.name || '';
                organiser.hidden = !data.permissions.organise;
                organiser.querySelector('form[data-action="add_member"]').closest('.panel').hidden = !data.permissions.manage;

                roleSelects.forEach((select) => {
                    if (select.options.length) return;
                    Object.keys(data.roles).forEach((key) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = data.roles[key];
                        option.selected = key === 'child';
                        select.appendChild(option);
                    });
                });

                const openTasks = data.tasks.filter((task) => task.is_done === '0');
                stats.tasks.textContent = openTasks.filter((task) => task.task_type !== 'appointment').length;
                stats.appointments.textContent = openTasks.filter((task) => task.task_type === 'appointment').length;
                stats.points.textContent = data.subject.can_organise ? data.members.reduce((sum, member) => sum + parseInt(member.points, 10), 0) : parseInt(data.subject.points, 10);

                memberSelects.forEach((select) => {
                    const first = select.querySelector('option');
                    select.innerHTML = '';
                    select.appendChild(first);
                    data.members.forEach((member) => {
                        const option = document.createElement('option');
                        option.value = member.id;
                        option.textContent = member.name;
                        select.appendChild(option);
                    });
                });

                taskList.innerHTML = data.tasks.length ? '' : '<li class="empty"><?php echo esc_js( __( 'No tasks yet.', 'family-manager' ) ); ?></li>';
                data.tasks.forEach((task) => {
                    const item = document.createElement('li');
                    item.className = 'item' + (task.is_done === '1' ? ' done' : '');
                    item.innerHTML = '<button class="secondary" type="button">' + (task.is_done === '1' ? '<?php echo esc_js( __( 'Undo', 'family-manager' ) ); ?>' : '<?php echo esc_js( __( 'Done', 'family-manager' ) ); ?>') + '</button><div><div class="title"></div><div class="meta"></div></div><span class="pill"></span>';
                    item.querySelector('.title').textContent = task.title;
                    item.querySelector('.meta').textContent = [task.member_name || '<?php echo esc_js( __( 'Household', 'family-manager' ) ); ?>', task.due_date || '<?php echo esc_js( __( 'No date', 'family-manager' ) ); ?>'].join(' · ');
                    item.querySelector('.pill').textContent = task.task_type === 'appointment' ? '<?php echo esc_js( __( 'Appointment', 'family-manager' ) ); ?>' : '+' + task.points;
                    item.querySelector('button').addEventListener('click', () => save({ family_action: 'toggle_task', task_id: task.id }));
                    taskList.appendChild(item);
                });

                memberList.innerHTML = data.members.length ? '' : '<li class="empty"><?php echo esc_js( __( 'Add members to assign tasks and rewards.', 'family-manager' ) ); ?></li>';
                data.members.forEach((member) => {
                    const item = document.createElement('li');
                    const isSelf = member.id === data.viewer.id;
                    item.className = 'item';
                    item.innerHTML = '<div class="member-actions"></div><a class="member-link"><div class="title"></div><div class="meta"></div></a><span class="pill"></span>';
                    item.querySelector('.title').textContent = member.name;
                    item.querySelector('.meta').textContent = member.role_label + ' · @' + member.login;
                    item.querySelector('.pill').textContent = member.points + ' pts';
                    const link = item.querySelector('.member-link');
                    if (data.permissions.manage && !isSelf) {
                        link.href = window.familyManager.memberUrl + member.id + '/';
                        link.title = '<?php echo esc_js( __( 'View the app as this member', 'family-manager' ) ); ?>';
                    }
                    if (data.permissions.manage && !isSelf && !data.permissions.viewing_as_other) {
                        const actions = item.querySelector('.member-actions');
                        const select = document.createElement('select');
                        Object.keys(roleLabels).forEach((key) => {
                            const option = document.createElement('option');
                            option.value = key;
                            option.textContent = roleLabels[key];
                            option.selected = key === member.role;
                            select.appendChild(option);
                        });
                        select.addEventListener('change', () => save({ family_action: 'set_member_role', member_id: member.id, role: select.value }));
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'secondary';
                        remove.textContent = '×';
                        remove.title = '<?php echo esc_js( __( 'Remove from household', 'family-manager' ) ); ?>';
                        remove.addEventListener('click', () => {
                            if (confirm('<?php echo esc_js( __( 'Remove this member from the household? Their account is kept.', 'family-manager' ) ); ?>')) {
                                save({ family_action: 'remove_member', member_id: member.id });
                            }
                        });
                        actions.appendChild(select);
                        actions.appendChild(remove);
                    }
                    memberList.appendChild(item);
                });

                rewardList.innerHTML = data.rewards.length ? '' : '<li class="empty"><?php echo esc_js( __( 'No rewards yet.', 'family-manager' ) ); ?></li>';
                data.rewards.forEach((reward) => {
                    const item = document.createElement('li');
                    item.className = 'item';
                    item.innerHTML = '<div></div><div><div class="title"></div><div class="meta"></div></div><span class="pill"></span>';
                    item.querySelector('.title').textContent = reward.title;
                    item.querySelector('.meta').textContent = reward.member_name || '<?php echo esc_js( __( 'Any member', 'family-manager' ) ); ?>';
                    item.querySelector('.pill').textContent = reward.points + ' pts';
                    rewardList.appendChild(item);
                });

                status.textContent = data.household.name + (data.permissions.viewing_as_other ? '' : ' · ' + data.viewer.role_label);
            }

            function load() {
                status.textContent = '<?php echo esc_js( __( 'Loading household...', 'family-manager' ) ); ?>';
                request({ family_action: 'get' }).then((result) => {
                    if (!result.success) {
                        throw new Error(result.data && result.data.message ? result.data.message : 'Request failed');
                    }
                    render(result.data);
                }).catch((error) => {
                    status.textContent = error.message;
                });
            }

            function save(payload) {
                status.textContent = '<?php echo esc_js( __( 'Saving...', 'family-manager' ) ); ?>';
                request(payload).then((result) => {
                    if (!result.success) {
                        throw new Error(result.data && result.data.message ? result.data.message : 'Request failed');
                    }
                    render(result.data);
                }).catch((error) => {
                    status.textContent = error.message;
                });
            }

            app.querySelectorAll('form[data-action]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const payload = { family_action: form.dataset.action };
                    new FormData(form).forEach((value, key) => payload[key] = value);
                    save(payload);
                    form.reset();
                });
            });

            load();
        })();
    </script>

    <?php wp_app_body_close(); ?>
</body>
</html>
