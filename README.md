# Households

A WordPress app powered by [WpApp](https://github.com/akirk/wp-app) for running a household — or several. Most family apps assume one home; this one is built for the families that have more than one.

[Try Households in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint.json) · [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## How it works

- **A home is a term; everything in it is a post tagged with that term.** People, the facts a house needs you to know, the things kept there, what needs doing — one join answers every question about a home, and a question about several homes at once is the same join with more terms in it. The taxonomy is registered closed, because terms are global in a way private posts are not.

  | | |
  |---|---|
  | `/households/` | every home you belong to, and who is under each roof today |
  | `/households/<id>/` | one home: its people, tasks, facts and things |
  | `/households/<id>/manage/` | who is in it, who administers it, what it is called |
  | `/households/<id>/as/<person id>/` | that home as one of its people sees it |
  | `/households/where/` | everyone across your homes, where they are today, and the fortnight ahead |
  | `/households/things/` | everything kept across your homes, and which house it is at |
  | `/households/person/<person id>/` | a person, and what travels with them between homes |

  Belong to one home and the index takes you straight into it. Ask for a home you do not belong to and you are sent back to the ones you do.

- **A person is a post, and the WordPress account is optional.** The record is the person; the login is identity attached to it when they actually need to sign in — `post_author`, or nobody at all. That is how a toddler whose shoe size is worth writing down, or a relative who will never log in, gets to exist here without an account they would never use. Someone with an email gets an account with the `Household Member` role, which can do nothing but log in and open the app; an existing account is linked by email instead.

- **What travels with a person is prose, not fields.** Sizes, allergies, what the next person needs to know — it is the post's own content, so every edit is kept as a revision and a size written down with a date still says something a year later. A field that is silently overwritten hides its own age; a dated line does not.

- **Two decisions, not five roles.** Whether someone *administers a home* is per home and lives in term meta. Whether someone is a *child* is a property of the person, true wherever they go. Both are asked through the ordinary capability API — `current_user_can( 'manage_household', $home_id )` — so nothing in the plugin invents its own permission language.

- **Who is where** is the overview of people: everyone across the homes you belong to, listed once each — someone in three of your homes is one person, not three entries — with where they are today, then a day-by-day board for the next fortnight and the handovers that follow from it. People moving together on the same day are one handover, because that is one trip. It spans homes, so it says which one it is reading from and lets you pick another.
  - Anyone who belongs to more than one home can have a **rotation**: children between separated parents, but equally a week at the grandparents or a share in a holiday house. A rotation names its homes in order — as many as the family uses, not just two — and repeats a cycle of days. Pick week on/week off, 2-2-3, every other weekend, or set the fourteen days yourself.
  - The rotation is stored on the person rather than on any one home, so every home reads the same answer.
  - A single day is moved by tapping it on the board. That override wins over the pattern and leaves it untouched, so a swapped weekend does not shift every week after it.

- **Belonging to a home is not being at it.** A rotation says where someone is. Without one, a person who belongs to a single home is at it, because there is nowhere else they could be — but someone who belongs to several and rotates between none is simply not tracked, and the app says so rather than claiming they are in three places at once.

- **About this home** holds what people ask when they are in a house they do not live in every day: the wifi network, where the water main valve is, which day the bins go out. It belongs to the home because it is true of the place, and everyone there can read it — so it is not the place for anything that should not be shared with all of them.

- **Things kept here** is the same record in another mood: what lives in the house, and where. A thing is in one place at a time, so it is not removed from a list but **moved to another home** — the term is replaced rather than added. `/households/things/` shows everything across your homes at once, which is where you look when you cannot find the thing.

- **Leaving is not deleting.** Taking someone out of a home removes the membership; the record and everything written on it stays, because a child's history should not evaporate when an arrangement changes.

This started as Family Manager, was renamed to Households, and has since been rebuilt on the model above. There is no upgrade path from either — this is a prototype, so start it fresh.

The demo blueprint (`demo.json`) seeds three homes — two separated parents and a grandparent — with three children who belong to all of them, rotating week on and week off between the parents with a one-off day at the grandparent's. The youngest has no account at all. The member accounts use the password `demo`.
