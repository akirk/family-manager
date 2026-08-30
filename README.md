# Households

A WordPress app powered by [WpApp](https://github.com/akirk/wp-app) for running a household — or several. Most family apps assume one home; this one is built for the families that have more than one.

[Try Households in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint.json) · [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/households/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## How it works

- **Every home has its own address.** A home is somewhere you go, not a mode you switch into:

  | | |
  |---|---|
  | `/households/` | every home you belong to, and who is under each roof today |
  | `/households/<id>/` | one home: its tasks, appointments, members and notes |
  | `/households/<id>/manage/` | its members, their roles, and its settings |
  | `/households/<id>/as/<user id>/` | that home as one of its members sees it |
  | `/households/where/` | who is where, day by day, across your homes |
  | `/households/profile/<user id>/` | a person, and what travels with them between homes |

  Belong to one home and the index takes you straight into it. Ask for a home you do not belong to and you are sent back to the ones you do. Nothing remembers a "current household" beyond where you were last, which is only used to pick a landing spot and to give the cross-home views somewhere to look from.
- **Every member is a WordPress user.** Adding a member creates an account with the `Household Member` role (it can only log in and open the app). Members with an existing account are linked by email instead.
- **Households** are private posts; their membership is a map of user → household role (Administrator, Parent, Child, Grandparent, Caregiver). Roles are per household, so the same person can be an administrator of one household and a plain member of another.
- **Each member sees their own version of a home**: children see only the tasks assigned to them or to the whole household; organisers (parents and up) see everything and can add tasks.
- **Who is where** lives at `/households/where/`: a dial showing where everyone is right now, a day-by-day board for the next fortnight, and the handovers that follow from it. People moving together on the same day are one handover, because that is one trip. It spans homes, so it says which one it is reading from and lets you pick another.
  - Anyone who belongs to more than one household can have a **rotation**: children between separated parents, but equally a week at the grandparents or a share in a holiday house. A rotation names its homes in order — as many as the family uses, not just two — and repeats a cycle of days. Pick week on/week off, 2-2-3, every other weekend, or set the fourteen days yourself across every home in the list.
  - The rotation is stored on the member rather than on any one household, so every home reads the same answer.
  - A single day is moved by tapping it on the board. That override wins over the pattern and leaves it untouched, so a swapped weekend does not shift every week after it.
  - Everyone in the household sees the board; parents and up set the rotations and move days.
- **About this home**, on a home's manage page, holds what people ask when they are in a house they do not live in every day: the wifi network, where the water main valve is, which day the bins go out. It belongs to the household because it is true of the place, and everyone in that household can read it — so it is not the place for anything that should not be shared with all of them.
- **Chores and appointments** are a plain shared to-do list per household: a task belongs to the home, is optionally assigned to one member, and is ticked off by whoever does it.
- Tasks are private posts tagged with a `household_member` term per user; rotations and their one-off changes are user meta.

This plugin used to be called Family Manager, and the rename went all the way down: post types, taxonomy, role and meta keys. There is no upgrade path from an install that predates it — this is a prototype, so start it fresh.

The demo blueprint (`demo.json`) seeds three homes — two separated parents and a grandparent — with two children who belong to all of them, rotating week on and week off between the parents with a one-off day at the grandparent's, plus the notes each house needs; the member accounts use the password `demo`.
