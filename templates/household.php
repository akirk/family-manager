<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title(); ?></title>
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
        button { min-height: 40px; padding: 0 16px; border: 1px solid var(--fm-accent-strong); border-radius: 6px; background: var(--fm-accent); color: #fff; font-weight: 700; cursor: pointer; }
        button.secondary { background: transparent; color: var(--fm-accent-strong); }
        input:not([type="checkbox"]), select { width: 100%; min-height: 40px; padding: 0 10px; border: 1px solid var(--fm-line); border-radius: 6px; background: var(--fm-surface); color: var(--fm-text); }
        main { width: min(980px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 40px; }
        .back { display: inline-block; margin-bottom: 12px; color: var(--fm-accent-strong); font-weight: 700; text-decoration: none; }
        h1 { margin: 0; font-size: clamp(1.8rem, 5vw, 3rem); line-height: 1.1; }
        .subtitle { margin: 6px 0 20px; color: var(--fm-muted); }
        .status { color: var(--fm-muted); font-size: 0.92rem; min-height: 22px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .stat { border-left: 4px solid var(--fm-accent); background: var(--fm-surface); border-radius: 6px; padding: 12px; }
        .stat:nth-child(2) { border-color: var(--fm-blue); }
        .stat:nth-child(3) { border-color: var(--fm-warm); }
        .stat strong { display: block; font-size: 1.6rem; line-height: 1; }
        .stat span { color: var(--fm-muted); font-size: 0.85rem; }
        .grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(280px, 1fr); gap: 16px; align-items: start; }
        .panel { background: var(--fm-surface); border: 1px solid var(--fm-line); border-radius: 8px; padding: 16px; }
        .panel + .panel { margin-top: 16px; }
        .panel h2 { margin: 0 0 12px; font-size: 1.05rem; }
        .panel p.hint { margin: -6px 0 12px; color: var(--fm-muted); font-size: 0.9rem; }
        form { display: grid; gap: 8px; }
        label.check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; }
        label.check input { margin-top: 5px; }
        label.check small { display: block; color: var(--fm-muted); }
        .member-list, .info-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
        .info-list .detail { white-space: pre-wrap; word-break: break-word; }
        .add-info { grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto; gap: 8px; margin-top: 12px; }
        @media (max-width: 620px) { .add-info { grid-template-columns: 1fr; } }
        .item { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; border: 1px solid var(--fm-line); border-radius: 6px; padding: 10px; }
        .title { font-weight: 750; }
        .meta { color: var(--fm-muted); font-size: 0.86rem; }
        .pill { display: inline-flex; align-items: center; min-height: 26px; padding: 0 8px; border-radius: 999px; background: color-mix(in srgb, var(--fm-accent) 12%, transparent); color: var(--fm-accent-strong); font-size: 0.82rem; font-weight: 700; white-space: nowrap; }
        .empty { color: var(--fm-muted); border: 1px dashed var(--fm-line); border-radius: 6px; padding: 18px; text-align: center; }
        .member-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; justify-content: flex-end; }
        .member-actions select { min-height: 32px; width: auto; }
        .member-actions button { min-height: 32px; padding: 0 8px; }
        .member-actions a { display: inline-flex; align-items: center; min-height: 32px; padding: 0 8px; border: 1px solid var(--fm-line); border-radius: 6px; color: var(--fm-accent-strong); font-size: 0.85rem; font-weight: 700; text-decoration: none; }
        [hidden] { display: none !important; }
        @media (max-width: 760px) {
            .grid { grid-template-columns: 1fr; }
            .item { grid-template-columns: 1fr; }
            .member-actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main id="households-household">
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Dashboard', 'households' ); ?></a>
        <h1 data-name><?php echo esc_html__( 'Household', 'households' ); ?></h1>
        <p class="subtitle" data-subtitle></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading household...', 'households' ); ?></div>

        <section class="stats" aria-label="<?php echo esc_attr__( 'Household summary', 'households' ); ?>">
            <div class="stat"><strong data-stat="members">0</strong><span><?php echo esc_html__( 'Members', 'households' ); ?></span></div>
            <div class="stat"><strong data-stat="tasks">0</strong><span><?php echo esc_html__( 'Open tasks', 'households' ); ?></span></div>
            <div class="stat"><strong data-stat="appointments">0</strong><span><?php echo esc_html__( 'Appointments', 'households' ); ?></span></div>
            <div class="stat" data-rewards-only hidden><strong data-stat="points">0</strong><span><?php echo esc_html__( 'Reward points', 'households' ); ?></span></div>
        </section>

        <section class="grid">
            <div>
                <div class="panel">
                    <h2><?php echo esc_html__( 'Members', 'households' ); ?></h2>
                    <ul class="member-list" data-members-list></ul>
                </div>
                <div class="panel">
                    <h2><?php echo esc_html__( 'About this home', 'households' ); ?></h2>
                    <p class="hint"><?php echo esc_html__( 'The things people ask when they are here without you: the wifi code, where the water main valve is, which day the bins go out. Everyone in this household can read it.', 'households' ); ?></p>
                    <ul class="info-list" data-info-list></ul>
                    <form class="add-info" data-action="add_household_info" data-organiser-only hidden>
                        <input name="label" required placeholder="<?php echo esc_attr__( 'Wifi, bin day, alarm code...', 'households' ); ?>">
                        <input name="detail" placeholder="<?php echo esc_attr__( 'The answer', 'households' ); ?>">
                        <button type="submit"><?php echo esc_html__( 'Add', 'households' ); ?></button>
                    </form>
                </div>
            </div>
            <div data-manage hidden>
                <div class="panel">
                    <h2><?php echo esc_html__( 'Add a member', 'households' ); ?></h2>
                    <p class="hint"><?php echo esc_html__( 'Creates a login for them. With an email, an existing account is linked instead.', 'households' ); ?></p>
                    <form data-action="add_member">
                        <input name="name" required placeholder="<?php echo esc_attr__( 'Name', 'households' ); ?>">
                        <select name="role" data-roles></select>
                        <input name="email" type="email" placeholder="<?php echo esc_attr__( 'Email (optional)', 'households' ); ?>">
                        <input name="password" type="text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Password (optional)', 'households' ); ?>">
                        <button type="submit"><?php echo esc_html__( 'Add member', 'households' ); ?></button>
                    </form>
                </div>
                <div class="panel">
                    <h2><?php echo esc_html__( 'Settings', 'households' ); ?></h2>
                    <form data-action="update_household" data-settings>
                        <input name="name" required aria-label="<?php echo esc_attr__( 'Household name', 'households' ); ?>">
                        <label class="check">
                            <input type="checkbox" name="rewards_enabled" value="1">
                            <span><?php echo esc_html__( 'Use points and rewards', 'households' ); ?><small><?php echo esc_html__( 'Tasks earn points that members can trade for rewards. Leave this off if you just want a shared to-do list.', 'households' ); ?></small></span>
                        </label>
                        <button type="submit"><?php echo esc_html__( 'Save settings', 'households' ); ?></button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <script>
        window.households = <?php echo wp_json_encode( [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'households_app' ),
            'memberUrl'  => home_url( '/households/member/' ),
            'profileUrl' => home_url( '/households/profile/' ),
        ] ); ?>;
    </script>
    <script>
        (function() {
            const root = document.getElementById('households-household');
            const status = root.querySelector('[data-status]');
            const memberList = root.querySelector('[data-members-list]');
            const infoList = root.querySelector('[data-info-list]');
            const manage = root.querySelector('[data-manage]');
            const settings = root.querySelector('[data-settings]');
            const roleSelect = root.querySelector('[data-roles]');
            const roleLabels = {};

            function request(payload) {
                const body = new FormData();
                body.append('action', 'households_dashboard');
                body.append('nonce', window.households.nonce);
                Object.keys(payload || {}).forEach((key) => body.append(key, payload[key]));
                return fetch(window.households.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                    .then((response) => response.json())
                    .then((result) => {
                        if (!result.success) {
                            throw new Error(result.data && result.data.message ? result.data.message : 'Request failed');
                        }
                        return result.data;
                    });
            }

            function roleOptions(select, selected) {
                select.innerHTML = '';
                Object.keys(roleLabels).forEach((key) => {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = roleLabels[key];
                    option.selected = key === selected;
                    select.appendChild(option);
                });
            }

            function render(data) {
                Object.assign(roleLabels, data.roles);
                const rewards = !!data.household.rewards_enabled;

                root.querySelector('[data-name]').textContent = data.household.name;
                root.querySelector('[data-subtitle]').textContent = data.permissions.manage
                    ? '<?php echo esc_js( __( 'You manage this household. Add members, change their roles and adjust settings here.', 'households' ) ); ?>'
                    : '<?php echo esc_js( __( 'Everyone in this household and how to reach their profile.', 'households' ) ); ?>';
                document.title = data.household.name + ' · ' + document.title.replace(/^.*· /, '');

                const openTasks = data.tasks.filter((task) => task.is_done === '0');
                root.querySelector('[data-stat="members"]').textContent = data.members.length;
                root.querySelector('[data-stat="tasks"]').textContent = openTasks.filter((task) => task.task_type !== 'appointment').length;
                root.querySelector('[data-stat="appointments"]').textContent = openTasks.filter((task) => task.task_type === 'appointment').length;
                root.querySelector('[data-stat="points"]').textContent = data.members.reduce((sum, member) => sum + parseInt(member.points, 10), 0);
                root.querySelectorAll('[data-rewards-only]').forEach((el) => el.hidden = !rewards);

                manage.hidden = !data.permissions.manage;
                if (!roleSelect.options.length) {
                    roleOptions(roleSelect, 'child');
                }
                if (document.activeElement === null || !settings.contains(document.activeElement)) {
                    settings.elements.name.value = data.household.name;
                    settings.elements.rewards_enabled.checked = rewards;
                }

                memberList.innerHTML = data.members.length ? '' : '<li class="empty"><?php echo esc_js( __( 'No members yet.', 'households' ) ); ?></li>';
                data.members.forEach((member) => {
                    const item = document.createElement('li');
                    const isSelf = member.id === data.viewer.id;
                    item.className = 'item';
                    item.innerHTML = '<div><div class="title"></div><div class="meta"></div></div><div class="member-actions"></div>';
                    item.querySelector('.title').textContent = member.name + (isSelf ? ' (<?php echo esc_js( __( 'you', 'households' ) ); ?>)' : '');
                    item.querySelector('.meta').textContent = [member.role_label, '@' + member.login].concat(rewards ? [member.points + ' <?php echo esc_js( __( 'pts', 'households' ) ); ?>'] : []).join(' · ');
                    const actions = item.querySelector('.member-actions');

                    if (isSelf || data.viewer.can_organise) {
                        const profile = document.createElement('a');
                        profile.href = window.households.profileUrl + member.id + '/';
                        profile.textContent = '<?php echo esc_js( __( 'Profile', 'households' ) ); ?>';
                        actions.appendChild(profile);
                    }
                    if (data.permissions.manage && !isSelf) {
                        const view = document.createElement('a');
                        view.href = window.households.memberUrl + member.id + '/';
                        view.textContent = '<?php echo esc_js( __( 'View as', 'households' ) ); ?>';
                        view.title = '<?php echo esc_js( __( 'See the dashboard as this member', 'households' ) ); ?>';
                        actions.appendChild(view);

                        const select = document.createElement('select');
                        select.setAttribute('aria-label', '<?php echo esc_js( __( 'Role', 'households' ) ); ?>');
                        roleOptions(select, member.role);
                        select.addEventListener('change', () => save({ household_action: 'set_member_role', member_id: member.id, role: select.value }));
                        actions.appendChild(select);

                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'secondary';
                        remove.textContent = '×';
                        remove.title = '<?php echo esc_js( __( 'Remove from household', 'households' ) ); ?>';
                        remove.addEventListener('click', () => {
                            if (confirm('<?php echo esc_js( __( 'Remove this member from the household? Their account is kept.', 'households' ) ); ?>')) {
                                save({ household_action: 'remove_member', member_id: member.id });
                            }
                        });
                        actions.appendChild(remove);
                    }
                    memberList.appendChild(item);
                });

                root.querySelectorAll('[data-organiser-only]').forEach((el) => el.hidden = !data.viewer.can_organise);
                infoList.innerHTML = (data.info || []).length ? '' : '<li class="empty"><?php echo esc_js( __( 'Nothing noted down yet.', 'households' ) ); ?></li>';
                (data.info || []).forEach((entry, index) => {
                    const item = document.createElement('li');
                    item.className = 'item';
                    item.innerHTML = '<div><div class="title"></div><div class="meta detail"></div></div><div class="member-actions"></div>';
                    item.querySelector('.title').textContent = entry.label;
                    item.querySelector('.detail').textContent = entry.detail;
                    if (data.viewer.can_organise) {
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'secondary';
                        remove.textContent = '×';
                        remove.title = '<?php echo esc_js( __( 'Remove this note', 'households' ) ); ?>';
                        remove.addEventListener('click', () => save({ household_action: 'remove_household_info', info_index: index }));
                        item.querySelector('.member-actions').appendChild(remove);
                    }
                    infoList.appendChild(item);
                });

                status.textContent = '';
            }

            function save(payload) {
                status.textContent = '<?php echo esc_js( __( 'Saving...', 'households' ) ); ?>';
                request(payload).then(render).catch((error) => status.textContent = error.message);
            }

            root.querySelectorAll('form[data-action]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const payload = { household_action: form.dataset.action };
                    new FormData(form).forEach((value, key) => payload[key] = value);
                    save(payload);
                    if (form.dataset.action.indexOf('add_') === 0) {
                        form.reset();
                    } else {
                        form.elements.name.blur();
                    }
                });
            });

            request({ household_action: 'get' }).then(render).catch((error) => status.textContent = error.message);
        })();
    </script>

    <?php wp_app_body_close(); ?>
</body>
</html>
