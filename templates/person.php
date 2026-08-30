<?php
$hh_person_id = (int) get_query_var( 'person_id' );
require __DIR__ . '/_head.php';
?>
        <a class="back" href="<?php echo esc_url( home_url( '/households/' ) ); ?>">&larr; <?php echo esc_html__( 'Your homes', 'households' ); ?></a>
        <h1 data-name><?php echo esc_html__( 'A person', 'households' ); ?></h1>
        <p class="subtitle" data-homes></p>
        <div class="status" data-status><?php echo esc_html__( 'Loading…', 'households' ); ?></div>

        <section>
            <h2><?php echo esc_html__( 'What travels with them', 'households' ); ?></h2>
            <p class="meta"><?php echo esc_html__( 'Sizes, allergies, medication, whatever the next person needs to know. Every home they belong to reads this same page, and every edit is kept, so a size written down with a date still says something in a year.', 'households' ); ?></p>
            <form data-form style="display:grid;gap:10px">
                <label><?php echo esc_html__( 'Born', 'households' ); ?><input type="date" name="birthdate"></label>
                <label><?php echo esc_html__( 'About', 'households' ); ?>
                    <small><?php echo esc_html__( 'Prose, not fields. Date what changes: “shoe size 32 — March 2026”.', 'households' ); ?></small>
                    <textarea name="about"></textarea>
                </label>
                <div><button class="primary" type="submit"><?php echo esc_html__( 'Save', 'households' ); ?></button></div>
            </form>
        </section>
    <script>
        (function() {
            const personId = <?php echo (int) $hh_person_id; ?>;
            const t = {
                belongs: '<?php echo esc_js( __( 'Belongs to:', 'households' ) ); ?>',
                nowhere: '<?php echo esc_js( __( 'Not in any home.', 'households' ) ); ?>',
                saved: '<?php echo esc_js( __( 'Saved. The previous version is kept.', 'households' ) ); ?>',
                age: '<?php echo esc_js( __( '%d years old', 'households' ) ); ?>',
                noAccount: '<?php echo esc_js( __( 'No account — nobody logs in as them.', 'households' ) ); ?>',
            };
            const form = document.querySelector('[data-form]');
            const nodes = {
                name: document.querySelector('[data-name]'),
                homes: document.querySelector('[data-homes]'),
            };

            function render(person) {
                nodes.name.textContent = person.name;
                const bits = [];
                bits.push(person.homes.length
                    ? t.belongs + ' ' + person.homes.map((home) => home.name).join(', ')
                    : t.nowhere);
                if (person.age !== null) { bits.push(t.age.replace('%d', person.age)); }
                if (!person.user_id) { bits.push(t.noAccount); }
                nodes.homes.textContent = bits.join(' · ');
                form.birthdate.value = person.birthdate || '';
                form.about.value = person.about || '';
                hh.say('');
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                hh.post('save_person', {
                    person_id: personId,
                    about: form.about.value,
                    birthdate: form.birthdate.value,
                }).then((data) => { render(data.person); hh.say(t.saved); })
                  .catch((error) => hh.say(error.message, true));
            });

            hh.post('get_person', { person_id: personId })
                .then((data) => render(data.person))
                .catch((error) => hh.say(error.message, true));
        })();
    </script>
<?php require __DIR__ . '/_foot.php'; ?>
