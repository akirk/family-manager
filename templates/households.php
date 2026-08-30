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
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: var(--fm-bg); color: var(--fm-text); line-height: 1.5; }
        main { width: min(860px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 40px; }
        .back { display: inline-block; margin-bottom: 12px; color: var(--fm-accent-strong); font-weight: 700; text-decoration: none; }
        h1 { margin: 0; font-size: clamp(1.8rem, 5vw, 3rem); line-height: 1.1; }
        .subtitle { margin: 6px 0 20px; color: var(--fm-muted); }
        .status { color: var(--fm-muted); font-size: 0.92rem; min-height: 22px; }
        .household-list { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; }
        .household { background: var(--fm-surface); border: 1px solid var(--fm-line); border-radius: 8px; padding: 16px; display: grid; grid-template-columns: 1fr auto; gap: 12px; }
        .household.current { border-color: var(--fm-accent); box-shadow: inset 4px 0 0 var(--fm-accent); }
        .household h2 { margin: 0 0 2px; font-size: 1.15rem; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .meta { color: var(--fm-muted); font-size: 0.9rem; }
        .members { margin-top: 8px; }
        .pill { display: inline-flex; align-items: center; min-height: 24px; padding: 0 8px; border-radius: 999px; background: color-mix(in srgb, var(--fm-accent) 12%, transparent); color: var(--fm-accent-strong); font-size: 0.78rem; font-weight: 700; white-space: nowrap; }
        .actions { display: flex; flex-direction: column; gap: 6px; align-items: stretch; }
        .actions a { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 0 12px; border: 1px solid var(--fm-accent-strong); border-radius: 6px; color: var(--fm-accent-strong); font-size: 0.9rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .actions a.primary { background: var(--fm-accent); color: #fff; }
        .empty { color: var(--fm-muted); border: 1px dashed var(--fm-line); border-radius: 6px; padding: 18px; text-align: center; }
        [hidden] { display: none !important; }
        @media (max-width: 600px) {
            .household { grid-template-columns: 1fr; }
            .actions { flex-direction: row; flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main id="households-households">
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Dashboard', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Your households', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'You belong to more than one household. Pick which one the dashboard shows.', 'households' ); ?></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading...', 'households' ); ?></div>
        <ul class="household-list" data-households></ul>
    </main>

    <script>
        window.households = <?php echo wp_json_encode( [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'households_app' ),
            'dashboardUrl' => home_url( '/households/' ),
            'householdUrl' => home_url( '/households/household/' ),
        ] ); ?>;
    </script>
    <script>
        (function() {
            const root = document.getElementById('households-households');
            const status = root.querySelector('[data-status]');
            const list = root.querySelector('[data-households]');
            const fm = window.households;

            function switchUrl(base, id) {
                return base + (base.indexOf('?') === -1 ? '?' : '&') + 'household=' + id;
            }

            function render(data) {
                list.innerHTML = data.households.length ? '' : '<li class="empty"><?php echo esc_js( __( 'You are not in any household yet.', 'households' ) ); ?></li>';
                data.households.forEach((h) => {
                    const item = document.createElement('li');
                    item.className = 'household' + (h.is_current ? ' current' : '');
                    item.innerHTML = '<div><h2><span class="name"></span><span class="pill" hidden><?php echo esc_js( __( 'Current', 'households' ) ); ?></span></h2><div class="meta"></div><div class="meta members"></div></div><div class="actions"></div>';
                    item.querySelector('.name').textContent = h.name;
                    item.querySelector('.pill').hidden = !h.is_current;
                    const counts = [
                        (h.open_tasks === 1 ? '<?php echo esc_js( __( '1 open task', 'households' ) ); ?>' : '<?php echo esc_js( __( '%d open tasks', 'households' ) ); ?>'.replace('%d', h.open_tasks)),
                        (h.appointments === 1 ? '<?php echo esc_js( __( '1 appointment', 'households' ) ); ?>' : '<?php echo esc_js( __( '%d appointments', 'households' ) ); ?>'.replace('%d', h.appointments))
                    ];
                    item.querySelector('.meta').textContent = [h.role_label].concat(counts).join(' · ');
                    item.querySelector('.members').textContent = h.member_names.length
                        ? '<?php echo esc_js( __( 'Members:', 'households' ) ); ?> ' + h.member_names.join(', ')
                        : '<?php echo esc_js( __( 'No members yet.', 'households' ) ); ?>';

                    const actions = item.querySelector('.actions');
                    const open = document.createElement('a');
                    open.className = 'primary';
                    open.href = h.is_current ? fm.dashboardUrl : switchUrl(fm.dashboardUrl, h.id);
                    open.textContent = h.is_current ? '<?php echo esc_js( __( 'Open dashboard', 'households' ) ); ?>' : '<?php echo esc_js( __( 'Switch to this household', 'households' ) ); ?>';
                    actions.appendChild(open);
                    if (h.can_manage) {
                        const manage = document.createElement('a');
                        manage.href = h.is_current ? fm.householdUrl : switchUrl(fm.householdUrl, h.id);
                        manage.textContent = '<?php echo esc_js( __( 'Manage', 'households' ) ); ?>';
                        actions.appendChild(manage);
                    }
                    list.appendChild(item);
                });
                status.textContent = '';
            }

            const body = new FormData();
            body.append('action', 'households_dashboard');
            body.append('nonce', fm.nonce);
            body.append('household_action', 'get_households');
            fetch(fm.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                .then((response) => response.json())
                .then((result) => {
                    if (!result.success) {
                        throw new Error(result.data && result.data.message ? result.data.message : 'Request failed');
                    }
                    render(result.data);
                })
                .catch((error) => status.textContent = error.message);
        })();
    </script>

    <?php wp_app_body_close(); ?>
</body>
</html>
