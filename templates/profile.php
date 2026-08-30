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
        button, input, select, textarea { font: inherit; }
        button { min-height: 40px; padding: 0 16px; border: 1px solid var(--fm-accent-strong); border-radius: 6px; background: var(--fm-accent); color: #fff; font-weight: 700; cursor: pointer; }
        input, textarea { width: 100%; min-height: 40px; padding: 8px 10px; border: 1px solid var(--fm-line); border-radius: 6px; background: var(--fm-surface); color: var(--fm-text); }
        textarea { min-height: 96px; resize: vertical; }
        main { width: min(760px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 40px; }
        .back { display: inline-block; margin-bottom: 12px; color: var(--fm-accent-strong); font-weight: 700; text-decoration: none; }
        h1 { margin: 0; font-size: clamp(1.8rem, 5vw, 3rem); line-height: 1.1; }
        .subtitle { margin: 6px 0 20px; color: var(--fm-muted); }
        .status { color: var(--fm-muted); font-size: 0.92rem; min-height: 22px; }
        .panel { background: var(--fm-surface); border: 1px solid var(--fm-line); border-radius: 8px; padding: 16px; }
        form { display: grid; gap: 14px; }
        label { display: grid; gap: 4px; font-weight: 700; }
        label small { color: var(--fm-muted); font-weight: 400; }
        .two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .fact { border-left: 4px solid var(--fm-accent); background: var(--fm-surface); border-radius: 6px; padding: 10px 12px; }
        .fact strong { display: block; font-size: 1.2rem; line-height: 1.2; }
        .fact span { color: var(--fm-muted); font-size: 0.85rem; }
        .allergy { border-left-color: var(--fm-warm); }
        .actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        @media (max-width: 560px) { .two { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>
    <main id="households-profile">
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Dashboard', 'households' ); ?></a>
        <h1 data-name><?php echo esc_html__( 'Profile', 'households' ); ?></h1>
        <p class="subtitle" data-subtitle></p>

        <section class="facts" data-facts hidden>
            <div class="fact"><strong data-fact="age">–</strong><span><?php echo esc_html__( 'Age', 'households' ); ?></span></div>
            <div class="fact"><strong data-fact="shoe_size">–</strong><span><?php echo esc_html__( 'Shoe size', 'households' ); ?></span></div>
            <div class="fact"><strong data-fact="clothing_size">–</strong><span><?php echo esc_html__( 'Clothing size', 'households' ); ?></span></div>
            <div class="fact allergy"><strong data-fact="allergies">–</strong><span><?php echo esc_html__( 'Allergies', 'households' ); ?></span></div>
        </section>

        <div class="panel">
            <form data-profile-form>
                <div class="two">
                    <label><?php echo esc_html__( 'Birthday', 'households' ); ?><input type="date" name="birthdate"></label>
                    <label><?php echo esc_html__( 'Shoe size', 'households' ); ?><input name="shoe_size"></label>
                </div>
                <div class="two">
                    <label><?php echo esc_html__( 'Clothing size', 'households' ); ?><input name="clothing_size"></label>
                </div>
                <label><?php echo esc_html__( 'Allergies and intolerances', 'households' ); ?><small><?php echo esc_html__( 'One per line. Shown prominently to everyone in the household.', 'households' ); ?></small><textarea name="allergies"></textarea></label>
                <label><?php echo esc_html__( 'Quick notes', 'households' ); ?><small><?php echo esc_html__( 'Doctor, school, medication, whatever the next person needs to know.', 'households' ); ?></small><textarea name="notes"></textarea></label>
                <div class="actions">
                    <div class="status" data-status><?php echo esc_html__( 'Loading...', 'households' ); ?></div>
                    <button type="submit"><?php echo esc_html__( 'Save profile', 'households' ); ?></button>
                </div>
            </form>
        </div>
    </main>

    <script>
        window.households = <?php echo wp_json_encode( [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'households_app' ),
            'memberId' => (int) get_query_var( 'id' ),
        ] ); ?>;
    </script>
    <script>
        (function() {
            const root = document.getElementById('households-profile');
            const form = root.querySelector('[data-profile-form]');
            const status = root.querySelector('[data-status]');
            const facts = root.querySelector('[data-facts]');

            function request(payload) {
                const body = new FormData();
                body.append('action', 'households_dashboard');
                body.append('nonce', window.households.nonce);
                body.append('member_id', window.households.memberId);
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

            function render(data) {
                const p = data.profile;
                root.querySelector('[data-name]').textContent = p.name;
                root.querySelector('[data-subtitle]').textContent = [p.role_label, data.household.name, '@' + p.login].join(' · ');
                facts.hidden = false;
                root.querySelector('[data-fact="age"]').textContent = p.age === null ? '–' : p.age;
                root.querySelector('[data-fact="shoe_size"]').textContent = p.shoe_size || '–';
                root.querySelector('[data-fact="clothing_size"]').textContent = p.clothing_size || '–';
                root.querySelector('[data-fact="allergies"]').textContent = p.allergies ? p.allergies.split('\n').filter(Boolean).join(', ') : '<?php echo esc_js( __( 'None', 'households' ) ); ?>';
                ['birthdate', 'shoe_size', 'clothing_size', 'allergies', 'notes'].forEach((field) => {
                    form.elements[field].value = p[field] || '';
                });
                document.title = p.name + ' · ' + document.title.replace(/^.*· /, '');
                status.textContent = '';
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                status.textContent = '<?php echo esc_js( __( 'Saving...', 'households' ) ); ?>';
                const payload = { household_action: 'save_profile' };
                new FormData(form).forEach((value, key) => payload[key] = value);
                request(payload).then((data) => { render(data); status.textContent = '<?php echo esc_js( __( 'Saved.', 'households' ) ); ?>'; }).catch((error) => status.textContent = error.message);
            });

            request({ household_action: 'get_profile' }).then(render).catch((error) => status.textContent = error.message);
        })();
    </script>

    <?php wp_app_body_close(); ?>
</body>
</html>
