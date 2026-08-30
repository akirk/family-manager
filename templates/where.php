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
            --fm-plum: #6b3f7a;
            --fm-dial: #f0efe6;
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
                --fm-plum: #c39ad0;
                --fm-dial: #141a18;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: var(--fm-bg); color: var(--fm-text); line-height: 1.5; }
        button, input, select { font: inherit; }
        button { min-height: 40px; padding: 0 16px; border: 1px solid var(--fm-accent-strong); border-radius: 6px; background: var(--fm-accent); color: #fff; font-weight: 700; cursor: pointer; }
        button.secondary { background: transparent; color: var(--fm-accent-strong); }
        input:not([type="checkbox"]), select { width: 100%; min-height: 40px; padding: 0 10px; border: 1px solid var(--fm-line); border-radius: 6px; background: var(--fm-surface); color: var(--fm-text); }
        main { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 40px; }
        .back { display: inline-block; margin-bottom: 12px; color: var(--fm-accent-strong); font-weight: 700; text-decoration: none; }
        h1 { margin: 0; font-size: clamp(1.8rem, 5vw, 3rem); line-height: 1.1; }
        .subtitle { margin: 6px 0 20px; color: var(--fm-muted); }
        .status { color: var(--fm-muted); font-size: 0.92rem; min-height: 22px; }
        .panel { background: var(--fm-surface); border: 1px solid var(--fm-line); border-radius: 8px; padding: 16px; }
        .panel + .panel { margin-top: 16px; }
        .panel h2 { margin: 0 0 12px; font-size: 1.05rem; }
        .panel p.hint { margin: -6px 0 12px; color: var(--fm-muted); font-size: 0.9rem; }

        .seen-from { display: flex; flex-wrap: wrap; gap: 8px; align-items: baseline; margin: 0 0 16px; color: var(--fm-muted); font-size: 0.9rem; }
        .seen-from a { display: inline-flex; align-items: center; min-height: 30px; padding: 0 10px; border: 1px solid var(--fm-line); border-radius: 999px; color: var(--fm-accent-strong); font-weight: 700; text-decoration: none; }
        .seen-from a[aria-current="true"] { background: var(--fm-accent); border-color: var(--fm-accent-strong); color: #fff; }
        .clock-panel { display: grid; grid-template-columns: minmax(0, 360px) minmax(0, 1fr); gap: 20px; align-items: center; }
        .dial { width: 100%; max-width: 360px; height: auto; margin: 0 auto; display: block; }
        .dial .face { fill: var(--fm-dial); stroke: var(--fm-line); stroke-width: 2; }
        .dial .divider { stroke: var(--fm-line); stroke-width: 1.5; }
        .dial .tick { stroke: var(--fm-line); stroke-width: 1; }
        .dial .place { font-size: 12px; font-weight: 700; letter-spacing: 0.02em; }
        .dial .hand { stroke-width: 3; stroke-linecap: round; }
        .dial .who { font-size: 12.5px; font-weight: 750; fill: var(--fm-text); }
        .dial .hub { fill: var(--fm-text); }
        .dial .c0 { stroke: var(--fm-accent); fill: var(--fm-accent); }
        .dial .c1 { stroke: var(--fm-blue); fill: var(--fm-blue); }
        .dial .c2 { stroke: var(--fm-warm); fill: var(--fm-warm); }
        .dial .c3 { stroke: var(--fm-plum); fill: var(--fm-plum); }
        .now { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
        .now li { border-left: 4px solid var(--fm-blue); border-radius: 6px; padding: 8px 12px; background: color-mix(in srgb, var(--fm-blue) 7%, transparent); }
        .now li.here { border-left-color: var(--fm-accent); background: color-mix(in srgb, var(--fm-accent) 7%, transparent); }
        .now li.unknown { border-left-color: var(--fm-line); background: transparent; }
        .now strong { display: block; }
        .now span { color: var(--fm-muted); font-size: 0.88rem; }

        .board-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
        .board-nav { display: flex; gap: 6px; }
        .board-nav button { min-height: 34px; padding: 0 12px; }
        .scroller { overflow-x: auto; padding-bottom: 4px; }
        .board { display: grid; gap: 2px; min-width: 640px; }
        .board .cell { min-height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 0.78rem; font-weight: 700; }
        .board .head { flex-direction: column; gap: 0; line-height: 1.1; color: var(--fm-muted); font-weight: 700; background: transparent; }
        .board .head small { font-weight: 400; font-size: 0.72rem; }
        .board .head.today { color: var(--fm-accent-strong); }
        .board .head.weekend small { color: var(--fm-warm); }
        .board .name { justify-content: flex-start; padding-right: 8px; font-weight: 750; white-space: nowrap; position: sticky; left: 0; background: var(--fm-surface); }
        .board .day { border: 1px solid transparent; color: #fff; }
        .board button.day { cursor: pointer; padding: 0; min-height: 34px; }
        .board .day.c0 { background: var(--fm-accent); border-color: var(--fm-accent-strong); }
        .board .day.c1 { background: var(--fm-blue); border-color: var(--fm-blue); }
        .board .day.c2 { background: var(--fm-warm); border-color: var(--fm-warm); }
        .board .day.c3 { background: var(--fm-plum); border-color: var(--fm-plum); }
        .board .day.none { background: transparent; border: 1px dashed var(--fm-line); color: var(--fm-muted); }
        .board .day.today { outline: 2px solid var(--fm-text); outline-offset: -2px; }
        .legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; color: var(--fm-muted); font-size: 0.85rem; }
        .legend span { display: inline-flex; align-items: center; gap: 6px; }
        .swatch { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
        .swatch.c0 { background: var(--fm-accent); }
        .swatch.c1 { background: var(--fm-blue); }
        .swatch.c2 { background: var(--fm-warm); }
        .swatch.c3 { background: var(--fm-plum); }

        .list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
        .item { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; border: 1px solid var(--fm-line); border-radius: 6px; padding: 10px; }
        .title { font-weight: 750; }
        .meta { color: var(--fm-muted); font-size: 0.86rem; }
        .pill { display: inline-flex; align-items: center; min-height: 26px; padding: 0 8px; border-radius: 999px; background: color-mix(in srgb, var(--fm-accent) 12%, transparent); color: var(--fm-accent-strong); font-size: 0.82rem; font-weight: 700; white-space: nowrap; }
        .pill.out { background: color-mix(in srgb, var(--fm-warm) 16%, transparent); color: var(--fm-warm); }
        .empty { color: var(--fm-muted); border: 1px dashed var(--fm-line); border-radius: 6px; padding: 18px; text-align: center; }

        details.rotation { border: 1px solid var(--fm-line); border-radius: 6px; padding: 10px 12px; }
        details.rotation + details.rotation { margin-top: 8px; }
        details.rotation summary { cursor: pointer; font-weight: 750; }
        details.rotation summary small { color: var(--fm-muted); font-weight: 400; }
        details.rotation form { display: grid; gap: 10px; margin-top: 12px; }
        .fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
        label.field { display: grid; gap: 4px; font-size: 0.86rem; font-weight: 700; color: var(--fm-muted); }
        .homes { display: grid; gap: 6px; }
        .home-row { display: grid; grid-template-columns: 26px 1fr auto; gap: 8px; align-items: center; }
        .home-row .ordinal { color: var(--fm-muted); font-size: 0.82rem; font-weight: 700; text-align: right; }
        .home-row button { min-height: 34px; padding: 0 10px; }
        .cycle { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .cycle label { display: grid; gap: 2px; justify-items: center; font-size: 0.7rem; color: var(--fm-muted); }
        .cycle select { min-height: 32px; padding: 0 2px; font-size: 0.78rem; }
        .form-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .form-actions button { min-height: 36px; }

        dialog { border: 1px solid var(--fm-line); border-radius: 8px; background: var(--fm-surface); color: var(--fm-text); padding: 16px; width: min(360px, calc(100vw - 32px)); }
        dialog::backdrop { background: rgba(0, 0, 0, 0.4); }
        dialog h3 { margin: 0 0 4px; font-size: 1.05rem; }
        dialog p { margin: 0 0 12px; color: var(--fm-muted); font-size: 0.88rem; }
        .choices { display: grid; gap: 8px; }
        .choices button { width: 100%; }
        [hidden] { display: none !important; }
        @media (max-width: 860px) {
            .clock-panel { grid-template-columns: 1fr; }
        }
        @media (max-width: 700px) {
            .item { grid-template-columns: 1fr; }
            .cycle { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main id="households-where">
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Your homes', 'households' ); ?></a>
        <h1><?php echo esc_html__( 'Who is where', 'households' ); ?></h1>
        <p class="subtitle"><?php echo esc_html__( 'Anyone who splits their time between homes — children between parents, a week at the grandparents, the holiday house — and the handovers that follow from it.', 'households' ); ?></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading...', 'households' ); ?></div>
        <p class="seen-from" data-seen-from hidden><span></span></p>

        <div class="panel">
            <div class="clock-panel">
                <svg class="dial" data-clock viewBox="0 0 320 320" role="img" aria-labelledby="dial-title" hidden>
                    <title id="dial-title"><?php echo esc_attr__( 'A dial showing which home each member is at today', 'households' ); ?></title>
                </svg>
                <div>
                    <h2 style="margin:0 0 10px;"><?php echo esc_html__( 'Today', 'households' ); ?></h2>
                    <ul class="now" data-now></ul>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="board-head">
                <h2 style="margin:0;"><?php echo esc_html__( 'Two weeks at a glance', 'households' ); ?></h2>
                <div class="board-nav">
                    <button type="button" class="secondary" data-nav="-14">&larr; <?php echo esc_html__( 'Earlier', 'households' ); ?></button>
                    <button type="button" class="secondary" data-nav="today"><?php echo esc_html__( 'Today', 'households' ); ?></button>
                    <button type="button" class="secondary" data-nav="14"><?php echo esc_html__( 'Later', 'households' ); ?> &rarr;</button>
                </div>
            </div>
            <p class="hint" data-board-hint hidden><?php echo esc_html__( 'Tap a day to move it to another home just this once. The pattern itself stays as it is.', 'households' ); ?></p>
            <div class="scroller"><div class="board" data-board></div></div>
            <div class="legend" data-legend></div>
        </div>

        <div class="panel">
            <h2><?php echo esc_html__( 'Handovers coming up', 'households' ); ?></h2>
            <ul class="list" data-handoffs></ul>
        </div>

        <div class="panel" data-organiser hidden>
            <h2><?php echo esc_html__( 'Rotations', 'households' ); ?></h2>
            <p class="hint"><?php echo esc_html__( 'A rotation is a repeating pattern across the homes someone belongs to. One-off changes belong in the board above.', 'households' ); ?></p>
            <div data-rotations></div>
        </div>
    </main>

    <dialog data-override-dialog>
        <h3 data-override-title></h3>
        <p data-override-date></p>
        <div class="choices" data-override-choices></div>
    </dialog>

    <script>
        window.households = <?php
        // This view spans homes, so it needs one to look from: the one asked
        // for, else the last one visited.
        $storage = new \Households\Storage();
        $viewer_id = get_current_user_id();
        $from = isset( $_GET['from'] ) ? absint( $_GET['from'] ) : 0;
        if ( ! $from || ! \Households\Access::is_member( $viewer_id, $from ) ) {
            $from = $storage->last_household_id( $viewer_id );
        }
        echo wp_json_encode( [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'households_app' ),
            'householdId' => $from,
            'baseUrl'     => home_url( '/households/' ),
            'whereUrl'    => home_url( '/households/where/' ),
        ] ); ?>;
        window.householdsText = <?php echo wp_json_encode( [
            'here'          => __( 'here', 'households' ),
            'seenFrom'      => __( 'Seen from', 'households' ),
            'isHere'        => __( '%s is here', 'households' ),
            'isAt'          => __( '%1$s is at %2$s', 'households' ),
            'untilDate'     => __( 'until %1$s, then %2$s', 'households' ),
            'lives'         => __( 'lives here', 'households' ),
            'noEnd'         => __( 'no change planned', 'households' ),
            'unknown'       => __( 'belongs to several homes, no rotation set', 'households' ),
            'noRotation'    => __( 'No rotation set', 'households' ),
            'noRotations'   => __( 'Nobody rotates between homes yet.', 'households' ),
            'noMembers'     => __( 'No members yet. Add them on the household page.', 'households' ),
            'noHandoffs'    => __( 'No handovers in the next four weeks.', 'households' ),
            'pickUp'        => __( 'Coming here', 'households' ),
            'dropOff'       => __( 'Leaving here', 'households' ),
            'elsewhere'     => __( 'Between other homes', 'households' ),
            'movesFrom'     => __( '%1$s → %2$s', 'households' ),
            'overrideTitle' => __( 'Where is %s that day?', 'households' ),
            'followPattern' => __( 'Follow the pattern', 'households' ),
            'oneOff'        => __( 'One-off', 'households' ),
            'needsTwoHomes' => __( '%s belongs to one home only, so there is nothing to rotate.', 'households' ),
            'saving'        => __( 'Saving...', 'households' ),
            'saved'         => __( 'Saved.', 'households' ),
            'startDate'     => __( 'Cycle starts', 'households' ),
            'changeover'    => __( 'Handover time', 'households' ),
            'pattern'       => __( 'Pattern', 'households' ),
            'homesLabel'    => __( 'Homes in this rotation', 'households' ),
            'addHome'       => __( 'Add a home', 'households' ),
            'removeHome'    => __( 'Remove', 'households' ),
            'firstTwoOnly'  => __( 'This pattern alternates between the first two homes. A custom cycle can use all of them.', 'households' ),
            'save'          => __( 'Save rotation', 'households' ),
            'remove'        => __( 'Remove rotation', 'households' ),
            'removeConfirm' => __( 'Remove this rotation? One-off changes for this member are removed too.', 'households' ),
            'cancel'        => __( 'Cancel', 'households' ),
        ] ); ?>;
    </script>
    <script>
        (function() {
            const root = document.getElementById('households-where');
            const text = window.householdsText;
            const status = root.querySelector('[data-status]');
            const clock = root.querySelector('[data-clock]');
            const nowList = root.querySelector('[data-now]');
            const board = root.querySelector('[data-board]');
            const boardHint = root.querySelector('[data-board-hint]');
            const legend = root.querySelector('[data-legend]');
            const handoffList = root.querySelector('[data-handoffs]');
            const organiser = root.querySelector('[data-organiser]');
            const rotations = root.querySelector('[data-rotations]');
            const dialog = document.querySelector('[data-override-dialog]');
            const seenFrom = root.querySelector('[data-seen-from]');
            const SVG = 'http://www.w3.org/2000/svg';

            let start = '';
            let colors = {};
            let openRotations = {};

            function sprintf(template, values) {
                let i = 0;
                return template.replace(/%(\d+\$)?s/g, (match, position) => {
                    const index = position ? parseInt(position, 10) - 1 : i++;
                    return values[index] === undefined ? '' : values[index];
                });
            }

            function request(payload) {
                const body = new FormData();
                body.append('action', 'households_dashboard');
                body.append('nonce', window.households.nonce);
                body.append('household_id', window.households.householdId);
                Object.keys(payload || {}).forEach((key) => {
                    if (Array.isArray(payload[key])) {
                        payload[key].forEach((value) => body.append(key + '[]', value));
                    } else {
                        body.append(key, payload[key]);
                    }
                });
                return fetch(window.households.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                    .then((response) => response.json())
                    .then((result) => {
                        if (!result.success) {
                            throw new Error(result.data && result.data.message ? result.data.message : 'Request failed');
                        }
                        return result.data;
                    });
            }

            function shortDate(date) {
                const parsed = new Date(date + 'T12:00:00');
                return parsed.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
            }

            /**
             * Every home gets a colour: the one we are looking from first, then the
             * rest in the order the server sends them. Taking it from that list
             * rather than from whatever the fortnight happens to contain keeps a
             * home the same colour as you page back and forth.
             */
            function assignColors(data) {
                colors = {};
                colors[data.household.id] = 0;
                let next = 1;
                data.board.households.forEach((home) => {
                    if (colors[home.id] === undefined) {
                        colors[home.id] = next++ % 4;
                    }
                });
            }

            /* ------------------------------------------------------------ The dial */

            function el(name, attributes, textContent) {
                const node = document.createElementNS(SVG, name);
                Object.keys(attributes || {}).forEach((key) => node.setAttribute(key, attributes[key]));
                if (textContent !== undefined) {
                    node.textContent = textContent;
                }
                return node;
            }

            function point(cx, cy, radius, degrees) {
                const radians = (degrees - 90) * Math.PI / 180;
                return [cx + radius * Math.cos(radians), cy + radius * Math.sin(radians)];
            }

            function shorten(value, max) {
                return value.length > max ? value.slice(0, max - 1) + '…' : value;
            }

            /**
             * One dial, one wedge per home, one hand per person: the quickest way to
             * read a household that is spread across several houses.
             */
            function renderClock(data) {
                const homes = data.board.households;
                const placed = data.board.members.filter((member) => member.now && member.now.household_id);
                // `hidden` is an HTMLElement property, so an SVG element needs the attribute.
                const nothingToShow = homes.length < 2 || !placed.length;
                clock.toggleAttribute('hidden', nothingToShow);
                if (nothingToShow) {
                    return;
                }

                while (clock.childNodes.length > 1) {
                    clock.removeChild(clock.lastChild);
                }

                const cx = 160, cy = 160, radius = 150;
                const wedge = 360 / homes.length;
                clock.appendChild(el('circle', { class: 'face', cx: cx, cy: cy, r: radius }));

                homes.forEach((home, index) => {
                    const centre = index * wedge;
                    const edge = centre - wedge / 2;
                    const [ex, ey] = point(cx, cy, radius, edge);
                    clock.appendChild(el('line', { class: 'divider', x1: cx, y1: cy, x2: ex, y2: ey }));

                    const [lx, ly] = point(cx, cy, radius - 26, centre);
                    const label = el('text', {
                        class: 'place c' + (colors[home.id] === undefined ? 3 : colors[home.id]),
                        // Keep a long house name inside the drawing, wherever its wedge sits.
                        x: Math.min(Math.max(lx, 60), 260),
                        y: ly,
                        'text-anchor': 'middle',
                        'dominant-baseline': 'middle',
                        stroke: 'none',
                    }, shorten(home.name, 14));
                    clock.appendChild(label);
                });

                // Hands share a wedge when people share a home, so fan them out.
                const perHome = {};
                placed.forEach((member) => {
                    const home = member.now.household_id;
                    (perHome[home] = perHome[home] || []).push(member);
                });

                Object.keys(perHome).forEach((homeId) => {
                    const index = homes.findIndex((home) => String(home.id) === String(homeId));
                    if (index < 0) {
                        return;
                    }
                    const group = perHome[homeId];
                    const spread = Math.min(wedge * 0.6, 20 * (group.length - 1));
                    group.forEach((member, position) => {
                        const offset = group.length === 1 ? 0 : (position - (group.length - 1) / 2) * (spread / (group.length - 1));
                        const angle = index * wedge + offset;
                        const length = radius - 62 - (position % 2) * 22;
                        const colour = 'c' + (colors[member.now.household_id] === undefined ? 3 : colors[member.now.household_id]);
                        const [hx, hy] = point(cx, cy, length, angle);
                        clock.appendChild(el('line', { class: 'hand ' + colour, x1: cx, y1: cy, x2: hx, y2: hy }));
                        clock.appendChild(el('circle', { class: colour, cx: hx, cy: hy, r: 4, stroke: 'none' }));
                        const [tx, ty] = point(cx, cy, length + 14, angle);
                        clock.appendChild(el('text', {
                            class: 'who',
                            x: tx,
                            y: ty,
                            'text-anchor': tx < cx - 6 ? 'end' : (tx > cx + 6 ? 'start' : 'middle'),
                            'dominant-baseline': 'middle',
                            stroke: 'none',
                        }, shorten(member.name, 12)));
                    });
                });

                clock.appendChild(el('circle', { class: 'hub', cx: cx, cy: cy, r: 9 }));
            }

            function renderNow(data) {
                // Who is here, then who is away, then whoever we cannot place.
                const rank = (member) => !member.now || !member.now.household_id ? 2 : (member.now.is_here ? 0 : 1);
                const members = data.board.members.slice().sort((a, b) => rank(a) - rank(b));
                nowList.innerHTML = '';
                if (!members.length) {
                    nowList.innerHTML = '<li class="empty">' + text.noMembers + '</li>';
                    return;
                }
                members.forEach((member) => {
                    const item = document.createElement('li');
                    const now = member.now && member.now.household_id ? member.now : null;
                    item.className = now ? (now.is_here ? 'here' : '') : 'unknown';
                    const headline = document.createElement('strong');
                    const detail = document.createElement('span');
                    if (!now) {
                        headline.textContent = member.name;
                        detail.textContent = member.can_rotate ? text.unknown : sprintf(text.needsTwoHomes, [member.name]);
                    } else {
                        headline.textContent = now.is_here ? sprintf(text.isHere, [member.name]) : sprintf(text.isAt, [member.name, now.household_name]);
                        detail.textContent = !member.has_rotation
                            ? text.lives
                            : (now.until ? sprintf(text.untilDate, [shortDate(now.until), now.next_name]) : text.noEnd);
                    }
                    item.appendChild(headline);
                    item.appendChild(detail);
                    nowList.appendChild(item);
                });
            }

            /* ------------------------------------------------------------ The board */

            function renderBoard(data) {
                const dates = data.board.dates;
                const members = data.board.members.filter((member) => member.has_rotation);
                board.innerHTML = '';
                board.style.gridTemplateColumns = 'minmax(90px, auto) repeat(' + dates.length + ', minmax(30px, 1fr))';
                boardHint.hidden = !data.permissions.organise || !members.length;

                if (!members.length) {
                    board.style.gridTemplateColumns = '1fr';
                    board.innerHTML = '<div class="empty">' + text.noRotations + '</div>';
                    legend.innerHTML = '';
                    return;
                }

                board.appendChild(document.createElement('div'));
                dates.forEach((date) => {
                    const head = document.createElement('div');
                    head.className = 'cell head' + (date.is_today ? ' today' : '') + (date.is_weekend ? ' weekend' : '');
                    head.innerHTML = '<span></span><small></small>';
                    head.querySelector('span').textContent = date.day;
                    head.querySelector('small').textContent = date.weekday;
                    board.appendChild(head);
                });

                members.forEach((member) => {
                    const name = document.createElement('div');
                    name.className = 'cell name';
                    name.textContent = member.name;
                    board.appendChild(name);

                    member.days.forEach((day, index) => {
                        const cell = document.createElement(data.permissions.organise ? 'button' : 'div');
                        const colorClass = day.household_id ? ' c' + (colors[day.household_id] === undefined ? 3 : colors[day.household_id]) : ' none';
                        cell.className = 'cell day' + colorClass + (dates[index] && dates[index].is_today ? ' today' : '');
                        cell.textContent = day.is_override ? '•' : '';
                        cell.title = member.name + ' · ' + shortDate(day.date) + ' · ' + (day.household_name || '–') + (day.is_override ? ' (' + text.oneOff + ')' : '');
                        if (data.permissions.organise) {
                            cell.type = 'button';
                            cell.addEventListener('click', () => openOverride(member, day));
                        }
                        board.appendChild(cell);
                    });
                });

                legend.innerHTML = '';
                data.board.households.forEach((home) => {
                    if (colors[home.id] === undefined) {
                        return;
                    }
                    const entry = document.createElement('span');
                    entry.innerHTML = '<i class="swatch c' + colors[home.id] + '"></i>';
                    entry.appendChild(document.createTextNode(home.name + (home.id === data.household.id ? ' (' + text.here + ')' : '')));
                    legend.appendChild(entry);
                });
                const oneOff = document.createElement('span');
                oneOff.textContent = '• ' + text.oneOff;
                legend.appendChild(oneOff);
            }

            function renderHandoffs(data) {
                const handoffs = data.board.handoffs;
                handoffList.innerHTML = handoffs.length ? '' : '<li class="empty">' + text.noHandoffs + '</li>';
                handoffs.forEach((handoff) => {
                    const item = document.createElement('li');
                    item.className = 'item';
                    item.innerHTML = '<div><div class="title"></div><div class="meta"></div></div><span class="pill"></span>';
                    item.querySelector('.title').textContent = handoff.members.join(', ') + ': ' + sprintf(text.movesFrom, [handoff.from_name, handoff.to_name]);
                    item.querySelector('.meta').textContent = shortDate(handoff.date) + ' · ' + handoff.time;
                    const pill = item.querySelector('.pill');
                    pill.textContent = handoff.direction === 'in' ? text.pickUp : (handoff.direction === 'out' ? text.dropOff : text.elsewhere);
                    pill.classList.toggle('out', handoff.direction === 'out');
                    handoffList.appendChild(item);
                });
            }

            /* ------------------------------------------------------------ Rotations */

            function renderRotations(data) {
                organiser.hidden = !data.permissions.organise;
                if (!data.permissions.organise) {
                    return;
                }

                rotations.innerHTML = '';
                const candidates = data.board.members.filter((member) => member.can_rotate || member.has_rotation);
                if (!candidates.length) {
                    rotations.innerHTML = '<div class="empty">' + (data.board.members.length ? text.noRotations : text.noMembers) + '</div>';
                    return;
                }

                candidates.forEach((member) => {
                    const details = document.createElement('details');
                    details.className = 'rotation';
                    details.open = !!openRotations[member.id];
                    details.addEventListener('toggle', () => openRotations[member.id] = details.open);

                    const summary = document.createElement('summary');
                    const pattern = data.board.patterns.find((entry) => entry.key === (member.rotation.pattern || ''));
                    summary.textContent = member.name + ' — ';
                    const note = document.createElement('small');
                    note.textContent = pattern ? pattern.label : text.noRotation;
                    summary.appendChild(note);
                    details.appendChild(summary);
                    details.appendChild(rotationForm(member, data));
                    rotations.appendChild(details);
                });
            }

            function rotationForm(member, data) {
                const form = document.createElement('form');
                const available = member.homes;
                const chosen = (member.rotation.homes && member.rotation.homes.length)
                    ? member.rotation.homes.slice()
                    : [data.household.id].concat(available.filter((home) => home.id !== data.household.id).slice(0, 1).map((home) => home.id));

                const fields = document.createElement('div');
                fields.className = 'fields';
                fields.appendChild(field(text.pattern, select('pattern', data.board.patterns.map((entry) => ({ value: entry.key, label: entry.label })), member.rotation.pattern || 'week')));
                const startInput = document.createElement('input');
                startInput.type = 'date';
                startInput.name = 'start_date';
                startInput.value = member.rotation.start_date || data.board.today;
                fields.appendChild(field(text.startDate, startInput));
                const timeInput = document.createElement('input');
                timeInput.type = 'time';
                timeInput.name = 'changeover_time';
                timeInput.value = member.rotation.changeover_time || '17:00';
                fields.appendChild(field(text.changeover, timeInput));
                form.appendChild(fields);

                // The homes are an ordered list, so a rotation can take in as many
                // places as the family uses, not just two.
                const homesLabel = document.createElement('div');
                homesLabel.className = 'field';
                homesLabel.style.fontWeight = '700';
                homesLabel.style.color = 'var(--fm-muted)';
                homesLabel.style.fontSize = '0.86rem';
                homesLabel.textContent = text.homesLabel;
                form.appendChild(homesLabel);

                const homeRows = document.createElement('div');
                homeRows.className = 'homes';
                form.appendChild(homeRows);

                const addHome = document.createElement('button');
                addHome.type = 'button';
                addHome.className = 'secondary';
                addHome.textContent = text.addHome;
                addHome.style.justifySelf = 'start';
                addHome.addEventListener('click', () => {
                    const used = currentHomes();
                    const spare = available.find((home) => used.indexOf(String(home.id)) < 0) || available[0];
                    chosenNow.push(spare.id);
                    drawHomes();
                    drawCycle();
                });
                form.appendChild(addHome);

                const hint = document.createElement('p');
                hint.className = 'hint';
                hint.style.margin = '0';
                form.appendChild(hint);

                const cycle = document.createElement('div');
                cycle.className = 'cycle';
                form.appendChild(cycle);

                let chosenNow = chosen.slice();

                function currentHomes() {
                    return [...homeRows.querySelectorAll('select')].map((element) => element.value);
                }

                function drawHomes() {
                    homeRows.innerHTML = '';
                    chosenNow.forEach((homeId, index) => {
                        const row = document.createElement('div');
                        row.className = 'home-row';
                        const ordinal = document.createElement('span');
                        ordinal.className = 'ordinal';
                        ordinal.textContent = (index + 1) + '.';
                        row.appendChild(ordinal);
                        const picker = select('homes[]', available.map((home) => ({ value: home.id, label: home.name })), homeId);
                        picker.addEventListener('change', () => {
                            chosenNow = currentHomes().map(Number);
                            drawCycle();
                        });
                        row.appendChild(picker);
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'secondary';
                        remove.textContent = '×';
                        remove.title = text.removeHome;
                        remove.hidden = chosenNow.length < 3;
                        remove.addEventListener('click', () => {
                            chosenNow.splice(index, 1);
                            drawHomes();
                            drawCycle();
                        });
                        row.appendChild(remove);
                        homeRows.appendChild(row);
                    });
                    addHome.hidden = chosenNow.length >= available.length;
                }

                function drawCycle() {
                    const patternKey = form.elements.pattern.value;
                    const entry = data.board.patterns.find((item) => item.key === patternKey);
                    const custom = patternKey === 'custom';
                    hint.textContent = entry
                        ? entry.start_hint + (!custom && chosenNow.length > 2 ? ' ' + text.firstTwoOnly : '')
                        : '';
                    cycle.hidden = !custom;
                    if (!custom) {
                        cycle.innerHTML = '';
                        return;
                    }
                    const stored = member.rotation.cycle;
                    const current = (stored && stored.length === 14) ? stored : entry.cycle;
                    const names = chosenNow.map((homeId) => {
                        const home = available.find((entry) => String(entry.id) === String(homeId));
                        return home ? home.name : '';
                    });
                    const from = form.elements.start_date.value;
                    cycle.innerHTML = '';
                    for (let day = 0; day < 14; day++) {
                        const label = document.createElement('label');
                        const caption = document.createElement('span');
                        // Naming the weekday makes a fourteen-day cycle readable.
                        const date = new Date(from + 'T12:00:00');
                        date.setDate(date.getDate() + day);
                        caption.textContent = isNaN(date) ? (day + 1) : date.toLocaleDateString(undefined, { weekday: 'short' });
                        const slot = current[day] < names.length ? current[day] : 0;
                        label.appendChild(caption);
                        label.appendChild(select('cycle[]', names.map((name, index) => ({ value: index, label: name })), slot));
                        cycle.appendChild(label);
                    }
                }

                form.elements.pattern.addEventListener('change', drawCycle);
                form.elements.start_date.addEventListener('change', drawCycle);

                const actions = document.createElement('div');
                actions.className = 'form-actions';
                const save = document.createElement('button');
                save.type = 'submit';
                save.textContent = text.save;
                actions.appendChild(save);
                if (member.has_rotation) {
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'secondary';
                    remove.textContent = text.remove;
                    remove.addEventListener('click', () => {
                        if (window.confirm(text.removeConfirm)) {
                            send({ household_action: 'clear_rotation', member_id: member.id });
                        }
                    });
                    actions.appendChild(remove);
                }
                form.appendChild(actions);

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const payload = { household_action: 'save_rotation', member_id: member.id, homes: [], cycle: [] };
                    new FormData(form).forEach((value, key) => {
                        if (key === 'homes[]') {
                            payload.homes.push(value);
                        } else if (key === 'cycle[]') {
                            payload.cycle.push(value);
                        } else {
                            payload[key] = value;
                        }
                    });
                    openRotations[member.id] = true;
                    send(payload);
                });

                drawHomes();
                drawCycle();
                return form;
            }

            function field(labelText, control) {
                const label = document.createElement('label');
                label.className = 'field';
                const caption = document.createElement('span');
                caption.textContent = labelText;
                label.appendChild(caption);
                label.appendChild(control);
                return label;
            }

            function select(name, options, selected) {
                const element = document.createElement('select');
                element.name = name;
                options.forEach((option) => {
                    const node = document.createElement('option');
                    node.value = option.value;
                    node.textContent = option.label;
                    node.selected = String(option.value) === String(selected);
                    element.appendChild(node);
                });
                return element;
            }

            function openOverride(member, day) {
                dialog.querySelector('[data-override-title]').textContent = sprintf(text.overrideTitle, [member.name]);
                dialog.querySelector('[data-override-date]').textContent = shortDate(day.date);
                const choices = dialog.querySelector('[data-override-choices]');
                choices.innerHTML = '';

                member.homes.forEach((home) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = home.id === day.household_id ? '' : 'secondary';
                    button.textContent = home.name;
                    button.addEventListener('click', () => {
                        dialog.close();
                        send({ household_action: 'set_override', member_id: member.id, date: day.date, override_household_id: home.id });
                    });
                    choices.appendChild(button);
                });

                if (day.is_override) {
                    const reset = document.createElement('button');
                    reset.type = 'button';
                    reset.className = 'secondary';
                    reset.textContent = text.followPattern;
                    reset.addEventListener('click', () => {
                        dialog.close();
                        send({ household_action: 'set_override', member_id: member.id, date: day.date, override_household_id: 0 });
                    });
                    choices.appendChild(reset);
                }

                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'secondary';
                cancel.textContent = text.cancel;
                cancel.addEventListener('click', () => dialog.close());
                choices.appendChild(cancel);

                dialog.showModal();
            }

            /** The board reads from one home; say which, and offer the others. */
            function renderSeenFrom(data) {
                const homes = data.households || [];
                seenFrom.hidden = homes.length < 2;
                if (seenFrom.hidden) {
                    return;
                }
                seenFrom.innerHTML = '<span></span>';
                seenFrom.querySelector('span').textContent = text.seenFrom;
                homes.forEach((home) => {
                    const link = document.createElement('a');
                    link.href = window.households.whereUrl + '?from=' + home.id;
                    link.textContent = home.name;
                    if (home.id === data.household.id) {
                        link.setAttribute('aria-current', 'true');
                    }
                    seenFrom.appendChild(link);
                });
            }

            function render(data) {
                start = data.board.start;
                assignColors(data);
                renderSeenFrom(data);
                renderClock(data);
                renderNow(data);
                renderBoard(data);
                renderHandoffs(data);
                renderRotations(data);
                document.title = data.household.name + ' · ' + document.title.replace(/^.*· /, '');
                status.textContent = data.household.name;
            }

            function send(payload) {
                status.textContent = text.saving;
                request(Object.assign({ start: start, window: 14 }, payload)).then((data) => {
                    render(data);
                    status.textContent = text.saved;
                }).catch((error) => status.textContent = error.message);
            }

            function load(from) {
                request({ household_action: 'get_whereabouts', start: from || '', window: 14 })
                    .then(render)
                    .catch((error) => status.textContent = error.message);
            }

            root.querySelectorAll('[data-nav]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (button.dataset.nav === 'today') {
                        load('');
                        return;
                    }
                    const moved = new Date(start + 'T12:00:00');
                    moved.setDate(moved.getDate() + parseInt(button.dataset.nav, 10));
                    load(moved.toISOString().slice(0, 10));
                });
            });

            load('');
        })();
    </script>

    <?php wp_app_body_close(); ?>
</body>
</html>
