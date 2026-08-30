<?php
/**
 * The chrome every page shares: styles, the app's config, and a small helper
 * for talking to the one AJAX endpoint. Pages set $hh_home_id before
 * including this, when the page is about one home.
 */
$hh_home_id = $hh_home_id ?? 0;
?>
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
            --hh-bg: #f6f7f2;
            --hh-text: #17201b;
            --hh-muted: #607067;
            --hh-surface: #ffffff;
            --hh-line: #d9e0d8;
            --hh-accent: #176b5b;
            --hh-accent-strong: #0f4f43;
            --hh-warm: #9b5d2f;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --hh-bg: #111614;
                --hh-text: #eef3ee;
                --hh-muted: #a7b3aa;
                --hh-surface: #1a211e;
                --hh-line: #344039;
                --hh-accent: #55b7a2;
                --hh-accent-strong: #87d4c2;
                --hh-warm: #d29561;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: var(--hh-bg); color: var(--hh-text); line-height: 1.5; }
        main { width: min(900px, calc(100% - 32px)); margin: 0 auto; padding: 24px 0 48px; }
        a { color: var(--hh-accent-strong); }
        .back { display: inline-block; margin-bottom: 10px; font-weight: 700; text-decoration: none; }
        h1 { margin: 0; font-size: clamp(1.6rem, 4.5vw, 2.6rem); line-height: 1.15; }
        .subtitle { margin: 6px 0 18px; color: var(--hh-muted); }
        .status { color: var(--hh-muted); font-size: 0.92rem; min-height: 22px; }
        .status[data-error] { color: var(--hh-warm); }
        section { background: var(--hh-surface); border: 1px solid var(--hh-line); border-radius: 8px; padding: 16px; margin: 0 0 16px; }
        section > h2 { margin: 0 0 10px; font-size: 1.05rem; }
        ul.plain { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .row { display: flex; gap: 10px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; }
        .meta { color: var(--hh-muted); font-size: 0.9rem; }
        .pill { display: inline-flex; align-items: center; min-height: 22px; padding: 0 8px; border-radius: 999px; background: color-mix(in srgb, var(--hh-accent) 12%, transparent); color: var(--hh-accent-strong); font-size: 0.76rem; font-weight: 700; white-space: nowrap; }
        .pill.warm { background: color-mix(in srgb, var(--hh-warm) 16%, transparent); color: var(--hh-warm); }
        .empty { color: var(--hh-muted); border: 1px dashed var(--hh-line); border-radius: 6px; padding: 16px; text-align: center; }
        button, .button { min-height: 38px; padding: 0 12px; border: 1px solid var(--hh-accent-strong); border-radius: 6px; background: transparent; color: var(--hh-accent-strong); font: inherit; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        button.primary { background: var(--hh-accent); border-color: var(--hh-accent); color: #fff; }
        button.quiet { border-color: var(--hh-line); color: var(--hh-muted); font-weight: 400; }
        input, select, textarea { min-height: 38px; width: 100%; padding: 6px 10px; border: 1px solid var(--hh-line); border-radius: 6px; background: var(--hh-bg); color: var(--hh-text); font: inherit; }
        textarea { min-height: 140px; resize: vertical; line-height: 1.6; }
        label { display: grid; gap: 4px; font-size: 0.9rem; font-weight: 700; }
        label small { font-weight: 400; color: var(--hh-muted); }
        label.inline { display: flex; align-items: center; gap: 8px; font-weight: 400; }
        label.inline input { width: auto; min-height: 0; }
        form.grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); align-items: end; }
        form.grid .wide { grid-column: 1 / -1; }
        .done { text-decoration: line-through; color: var(--hh-muted); }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>
    <script>
        window.households = <?php echo wp_json_encode( [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'households_app' ),
            'baseUrl' => home_url( '/households/' ),
            'homeId'  => (int) ( $hh_home_id ?? 0 ),
        ] ); ?>;
    </script>
    <script>
        /**
         * One endpoint, one helper. `post` sends an app action and resolves
         * with the payload; every page reports failures the same way.
         */
        window.hh = (function() {
            const cfg = window.households;
            const statusEl = () => document.querySelector('[data-status]');

            function say(message, isError) {
                const el = statusEl();
                if (!el) { return; }
                el.textContent = message || '';
                if (isError) { el.setAttribute('data-error', '1'); } else { el.removeAttribute('data-error'); }
            }

            function post(action, fields) {
                const body = new FormData();
                body.append('action', 'households_dashboard');
                body.append('nonce', cfg.nonce);
                body.append('household_action', action);
                if (cfg.homeId) { body.append('home_id', cfg.homeId); }
                Object.entries(fields || {}).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach((entry) => body.append(key + '[]', entry));
                    } else if (value !== undefined && value !== null) {
                        body.append(key, value);
                    }
                });
                return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                    .then((response) => response.json())
                    .then((result) => {
                        if (!result.success) {
                            throw new Error(result.data && result.data.message ? result.data.message : 'Request failed');
                        }
                        return result.data;
                    });
            }

            function el(tag, props, children) {
                const node = document.createElement(tag);
                Object.entries(props || {}).forEach(([key, value]) => {
                    if (key === 'text') { node.textContent = value; }
                    else if (key === 'html') { node.innerHTML = value; }
                    else if (key === 'onclick') { node.addEventListener('click', value); }
                    else if (value === true) { node.setAttribute(key, ''); }
                    else if (value !== false && value !== null && value !== undefined) { node.setAttribute(key, value); }
                });
                (children || []).forEach((child) => child && node.appendChild(child));
                return node;
            }

            function homeUrl(id, suffix) { return cfg.baseUrl + id + '/' + (suffix || ''); }
            function personUrl(id) { return cfg.baseUrl + 'person/' + id + '/'; }

            return { cfg, post, el, say, homeUrl, personUrl };
        })();
    </script>
    <main id="app">
