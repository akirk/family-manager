# Households

A WordPress app for running a household — or several. Most family apps assume
one home; this one is built for the families that have more than one. Built on
[WpApp](https://github.com/akirk/wp-app), so it runs as its own app at
`/households/` instead of inside wp-admin. A home is a term, everything in it
is a post tagged with that term, and there are no custom tables.

[Try Households in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint.json) · [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## What it does

- **An overview about you, not about your houses.** The front page answers what
  today wants from you: on the left what the household you are standing in
  still has open — everybody's, each line saying whose it is or that it is
  nobody's — and the things kept there; on the right your homes, the one you
  are at filled in, and the fortnight ahead as one dated list with
  appointments, handovers and birthdays merged. Filters and the add form live
  in the URL, so a filtered list can be handed to somebody else.
- **Saying where you are, from the overview.** Every household folds open to
  the people you may speak for — yourself, and anyone in a household you
  organise — as checkboxes, so a parent taking two children to the
  grandparents' is one press. It is about today alone and leaves the rotation
  untouched.
- **Who is where.** Everyone across your homes, listed once each, with where
  they are today, a day-by-day board for the next fortnight, and the handovers
  that follow from it. People moving together on the same day are one
  handover, because that is one trip.
- **Rotations.** Anyone in more than one home can have one: children between
  separated parents, a week at the grandparents, a share in a holiday house. A
  rotation names its homes in order — as many as the family uses — and repeats
  a 14-day cycle. Week on/week off, 2-2-3, alternate weekends, or set the
  fourteen days yourself. It is stored on the person, so every home reads the
  same answer.
- **Overrides by tapping a day.** A tap wins over the pattern and leaves it
  untouched, so a swapped weekend does not shift every week after it. Switched
  to *from that day onwards*, it clears whatever was arranged after it in the
  fortnight. For someone with no pattern at all, a stated day stands until the
  next stated day — somebody who moves on Tuesday is still there on Wednesday.
- **Belonging follows from being there.** Saying someone is at a household
  they are not in yet puts them in it. Leaving is separate and deliberate, and
  removes the membership only: the person record and everything written on it
  stays.
- **People without accounts.** A person is a post; the WordPress login is
  optional identity attached to it via `post_author`, or nobody at all. That is
  how a toddler whose shoe size is worth writing down gets a record. The plugin
  mints no accounts — they are made in WordPress and pointed at a person from
  the manage page.
- **What travels with a person.** Clothing and shoe size are fields, each
  stamped with the day the value last changed, so a size not measured since
  spring says so. Everything else — allergies, medication, what somebody
  minding them should know — is the post content, with revisions.
- **About this home.** The wifi network, where the water main valve is, which
  day the bins go out: facts that are true of the place, readable by everyone
  in it.
- **Things kept here, and where they have got to.** Where a thing *lives* is
  asked once per household, because the spare charger is in the hall drawer at
  one house and beside the bed at the other. Where it *is* is one mark on the
  thing itself, and may be a house that does not keep it — a thing taken along
  for the weekend is lent, not moved. A household page lists what it has a
  place for (saying which are away) plus a *Here just now* list of what has
  been brought to it.
- **Packing.** Marking a thing as going somewhere sits beside where it is
  rather than instead of it, and so does packing it: a thing in a bag by the
  door is still at the house it was always at. `/households/pack/` gathers
  everything going the same way as one list per trip, dated from the moves the
  fortnight already holds, and the whole bag is carried in one press. A trip
  with something waiting says so beside the move on the overview and beside the
  handover on the board.
- **Two decisions, not five roles.** Whether someone administers a home is per
  home and lives in term meta; whether someone is a child is a property of the
  person. Both are asked through the ordinary capability API —
  `current_user_can( 'manage_household', $home_id )` and
  `organise_household` — so the plugin invents no permission language of its
  own.

## Install

```bash
cd wp-content/plugins/households
composer install
```

Activate **Households** in WordPress and visit `/households/`. Requires PHP 7.4+
and a logged-in user.

## Development notes

- No build step. Every page is server-rendered PHP: it reads through `Storage`,
  and every change is a `<form method="post">` posting back to the page it was
  made on. A `template_redirect` handler checks the nonce, does the work, and
  redirects to the same URL, so a refresh repeats nothing and anything refused
  is named in the URL and said by the page.
- Nothing needs JavaScript. Two places use a little and neither depends on it:
  a `confirm()` on removing someone from a home, and the whereabouts board,
  which posts its own forms and splices the server's reply into the rows
  already on screen so paging a fortnight scrolls rather than reloads. Both
  scripts make the request the browser would have made on its own.
- `src/Access.php` — who may see or change what. `src/Storage.php` — every
  read and write. `src/Whereabouts.php` — rotations, overrides, handovers.
  `src/App.php` — routes and form handling. `src/View.php` — form helpers.
  Templates live in `templates/`.

```bash
php -l src/App.php
./vendor/bin/phpcs --standard=.phpcs.xml.dist src templates
```

## Status

This started as Family Manager, was renamed to Households, and has since been
rebuilt from scratch. There is no upgrade path from either — this is a
prototype, so start it fresh.

The demo blueprint (`demo.json`) seeds three homes — two separated parents and
a grandparent — with three children who belong to all of them, rotating week on
and week off between the parents with a one-off day at the grandparent's. The
youngest has no account at all. The member accounts use the password `demo`.

## License

GPL-2.0-or-later
