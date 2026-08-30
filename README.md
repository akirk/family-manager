# Family Manager

A WordPress app powered by [WpApp](https://github.com/akirk/wp-app) for managing a household's tasks, appointments, and rewards.

[Try Family Manager in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/family-manager/main/blueprint.json) · [Try it with demo data](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/family-manager/main/demo.json)

[Try it in OpenStation](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/akirk/family-manager/main/blueprint-openstation.json) — the same app opened in desktop mode with the [OpenStation](https://github.com/WordPress/openstation) plugin.

## How it works

- **Every family member is a WordPress user.** Adding a member creates an account with the `Family Member` role (it can only log in and open the app). Members with an existing account are linked by email instead.
- **Households** are private posts; their membership is a map of user → household role (Administrator, Parent, Child, Grandparent, Caregiver). Roles are per household, so the same person can be an administrator of one household and a plain member of another.
- **Each member has their own view** at `/family-manager/`: children see only the tasks and rewards assigned to them or to the whole household; organisers (parents and up) see everything and can add tasks and rewards.
- **Administrators can open any member's view** at `/family-manager/member/<user id>/` to check what that member sees and act on their behalf.
- Tasks and rewards are private posts tagged with a `family_member` term per user; points are user meta.

The demo blueprint (`demo.json`) seeds two homes (two separated parents, a grandparent, and two children who belong to both households); the member accounts use the password `demo`.
