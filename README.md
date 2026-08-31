# Households

A WordPress app powered by [WpApp](https://github.com/akirk/wp-app) for running a household — or several. Most family apps assume one home; this one is built for the families that have more than one.

[Try Households in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint.json) · [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## How it works

- **It is a WordPress plugin, not an app that happens to live in WordPress.** Every page is server-rendered PHP: it reads through `Storage`, prints its lists, and every change is a `<form method="post">` posting back to the page it was made on. A handler on `template_redirect` checks the nonce, does the work and redirects to the same URL, so a refresh repeats nothing and what comes back is simply the page read afresh; anything refused is named in the URL and said by the page. There is no build step, no endpoint the pages talk to, and nothing that needs JavaScript to work — the only script in the plugin is a `confirm()` on removing someone from a home, which without it simply submits.

- **A home is a term; everything in it is a post tagged with that term.** People, the facts a house needs you to know, the things kept there, what needs doing — one join answers every question about a home, and a question about several homes at once is the same join with more terms in it. The taxonomy is registered closed, because terms are global in a way private posts are not.

  | | |
  |---|---|
  | `/households/` | your day: where you are, what is yours to do, and the fortnight ahead |
  | `/households/homes/` | every home you belong to, and where a new one is started |
  | `/households/<id>/` | one home: its people, tasks, facts and things |
  | `/households/<id>/manage/` | who is in it, who administers it, what it is called |
  | `/households/<id>/as/<person id>/` | that home as one of its people sees it |
  | `/households/where/` | everyone across your homes, where they are today, and the fortnight ahead |
  | `/households/things/` | everything kept across your homes, and which house it is at |
  | `/households/person/<person id>/` | a person, and what travels with them between homes |

  Ask for a home you do not belong to and you are sent back to the ones you do.

  A home is started from `/households/homes/`, which settles the one thing a home cannot be without: someone who may add to it. Whoever starts it administers it and lands on its page to say who is in it — themselves included, with one button, because setting a household up is not the same as living in it. Somebody has to make the grandparents' house before anybody is put in it. Reaching a home is therefore being in it *or* administering it, and someone who already has a record joins with it — a second home does not make a second person. The name is not made unique: terms are global in a way families are not, so two households on one site may both have a "Home", and being refused — or told the other exists — would be wrong twice over. The slug is made unique instead, and the name is left alone.

- **The front page is about you, not about your houses.** A list of homes is a directory; what a person opens the app to ask is what today wants from them. So the index answers that: where you are and who is under that roof with you, what is asked of you by name and what the house has asked of nobody in particular — tickable from here, wherever it happens to be written down — and the fortnight ahead as one dated list. The appointments, the handovers and the birthdays are merged rather than kept in three sections, because a day is one thing to the person living it even when it is spread across three houses. When nothing can say where you are — you belong to several homes and rotate between none — it asks instead of shrugging: a button per home, and the answer counts for today alone. The homes themselves are one link away, at `/households/homes/`, which is where you go when you want one of them rather than your day.

- **A person is a post, and the WordPress account is optional.** The record is the person; the login is identity attached to it when they actually need to sign in — `post_author`, or nobody at all. That is how a toddler whose shoe size is worth writing down, or a relative who will never log in, gets to exist here without an account they would never use. The plugin makes no accounts: they are made in WordPress, by whoever runs the site, and pointed at a person afterwards from the manage page. One account answers for one person, and an account that stops being someone stops administering their households at the same moment, because administering is held as a user ID.

- **What travels with a person is prose, not fields.** Sizes, allergies, what the next person needs to know — it is the post's own content, so every edit is kept as a revision and a size written down with a date still says something a year later. A field that is silently overwritten hides its own age; a dated line does not.

- **Two decisions, not five roles.** Whether someone *administers a home* is per home and lives in term meta — and administering one is enough to open and organise it, membership or not. Whether someone is a *child* is a property of the person, true wherever they go. Both are asked through the ordinary capability API — `current_user_can( 'manage_household', $home_id )` — so nothing in the plugin invents its own permission language.

- **Who is where** is the overview of people: everyone across the homes you belong to, listed once each — someone in three of your homes is one person, not three entries — with where they are today, then a day-by-day board for the next fortnight and the handovers that follow from it. People moving together on the same day are one handover, because that is one trip. It spans homes, so it says which one it is reading from and lets you pick another.
  - Anyone who belongs to more than one home can have a **rotation**: children between separated parents, but equally a week at the grandparents or a share in a holiday house. A rotation names its homes in order — as many as the family uses, not just two — and repeats a cycle of days. Pick week on/week off, 2-2-3, every other weekend, or set the fourteen days yourself.
  - The rotation is stored on the person rather than on any one home, so every home reads the same answer.
  - A single day is moved by tapping it on the board. That override wins over the pattern and leaves it untouched, so a swapped weekend does not shift every week after it. The same tap says a day for someone who has no pattern at all.

- **Belonging to a home is not being at it, and being at one is how you come to belong.** Saying someone is at a household they are not in yet puts them in it: the first weekend at the grandparents is a move before it is an arrangement. Leaving stays deliberate and separate. A rotation says where someone is. Without one, a person who belongs to a single home is at it, because there is nowhere else they could be — but someone who belongs to several and rotates between none is not tracked, and rather than claim they are in three places at once, the app asks them. A day said outright needs no pattern behind it to be true: it is a statement about that one day, and it wins over anything a cycle works out. Saying where *you* are is something anyone may do about themselves, child or not; saying it about someone else is organising, and stays with the people who organise.

- **About this home** holds what people ask when they are in a house they do not live in every day: the wifi network, where the water main valve is, which day the bins go out. It belongs to the home because it is true of the place, and everyone there can read it — so it is not the place for anything that should not be shared with all of them.

- **Things kept here** is the same record in another mood: what lives in the house, and where. A thing is in one place at a time, so it is not removed from a list but **moved to another home** — the term is replaced rather than added. `/households/things/` shows everything across your homes at once, which is where you look when you cannot find the thing.

- **Leaving is not deleting.** Taking someone out of a home removes the membership; the record and everything written on it stays, because a child's history should not evaporate when an arrangement changes.

This started as Family Manager, was renamed to Households, and has since been rebuilt on the model above. There is no upgrade path from either — this is a prototype, so start it fresh.

The demo blueprint (`demo.json`) seeds three homes — two separated parents and a grandparent — with three children who belong to all of them, rotating week on and week off between the parents with a one-off day at the grandparent's. The youngest has no account at all. The member accounts use the password `demo`.
