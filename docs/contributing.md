# Contributing to GatherPress

GatherPress is built by and for the community — and we’d love your help! Whether you're a developer, designer, organizer, writer, or enthusiast, there’s a place for you here.

## 🤝 Ways to Contribute

- Report or fix issues
- Improve plugin functionality or accessibility
- Write or update documentation
- Translate GatherPress into other languages
- Test new features or submit feedback
- Review and comment on pull requests

## 🧠 Get Started

- 🐛 Browse [open issues](https://github.com/GatherPress/gatherpress/issues)
- 📘 Read the [Developer Docs](https://github.com/GatherPress/gatherpress/tree/develop/docs/developer)
- 🧪 Try the [Playground](./playground.md)
- 💬 Join us on [WordPress Slack](https://make.wordpress.org/chat/) or [GatherPress.org](https://gatherpress.org/get-involved)

## 📝 Changelog Entries

Every pull request that ships a user-visible change should include a changelog entry. The repository uses [`automattic/jetpack-changelogger`](https://github.com/Automattic/jetpack-changelogger) — each PR drops one small file into `.github/changelog/`, and at release time the entries are rolled up into `CHANGELOG.md` under a new version header. Per-PR files mean no merge conflicts on the changelog.

Three ways to add an entry:

1. **Locally** — run `composer changelog:add` and answer the prompts. The tool writes the file to `.github/changelog/` for you to commit.
2. **Via PR description** — check the "Automatically create a changelog entry from the details below" checkbox in the pull request template and fill in Significance, Type, and Message. CI parses those sections and creates the file on your branch (for fork PRs, CI posts a comment with the file content to commit manually).
3. **Skip the entry** — for changes that don't need a changelog line (e.g. CI tweaks, internal refactors, docs-only edits), add the `Skip Changelog` label to the PR.

Draft PRs and PRs not targeting `develop` are exempt from the check.

## 🔐 Access & Roles

- For GitHub write access, reach out to:
    - [@mauteri](https://github.com/mauteri)
    - [@MervinHernandez](https://github.com/MervinHernandez)

- For access to infrastructure (SSH or WP Admin on gatherpress.org), contact [@MervinHernandez](https://github.com/MervinHernandez)

## 🏷️ Contributor Credits

GatherPress credits contributors by their **WordPress.org username**, both on the in-plugin Credits screen and in the WordPress.org attribution. A [Props Bot](https://github.com/WordPress/props-bot-action) runs on every pull request and posts a maintained comment listing the PR's contributors (including authors of any linked issues), resolved to their wp.org usernames.

For the bot to resolve you correctly, **link your GitHub account on your [profiles.wordpress.org](https://profiles.wordpress.org/) profile**. If you don't have a WordPress.org account yet, create a free one at [login.wordpress.org/register](https://login.wordpress.org/register). Contributors who haven't linked their accounts won't resolve cleanly and may need to be credited by hand.

Maintainers can force the bot to refresh its list by adding the `Props Bot` label to a pull request.

Crediting is automated from there: when a pull request merges into `develop`, the Credits Sync workflow reads the Props Bot comment and appends anyone not yet credited for the current release cycle to `.github/scripts/release/credits/unreleased.json`, via a small auto-merging PR. At the next version bump those names move into the release's credits file and appear on the in-plugin Credits screen. Contributors the bot couldn't resolve (no linked GitHub account) are skipped by the automation — maintainers add them by hand to `unreleased.json` or the version's credits file.

### How credits are grouped

The Credits screen has three groups, and every release is credited on its own terms.

**Leads** are the people responsible for the direction of the project. This is a standing role and changes rarely.

**Noteworthy Contributors** are the people whose work shaped a given release. The bar is not the size of any single contribution, it is showing up repeatedly across a cycle: pull requests landing, issues opened and triaged, reviews given, discussion that moves decisions forward. It also covers work that never appears in git — helping in Slack, shaping ideas, organizing, translating, supporting other contributors. The leads make that call, because it is the part no tool can see.

**Contributors to GatherPress X.Y.Z** is everyone who contributed to that release, however small. A single typo fix belongs here, and belongs here genuinely — it is a real credit, sized to the contribution.

### Moving between groups

Getting into Noteworthy takes a pattern of contribution, not one good week. Staying there is deliberately easier than getting there: a quiet release does not cost you your place, because contribution comes in waves and people have jobs and lives.

Someone comes off the Noteworthy list after roughly three consecutive releases with no contribution of any kind. That is a few months of dormancy, and it is a statement about the release being credited rather than a judgement about the person. Anyone who returns and contributes goes back on.

Two things worth being direct about. This is not a permanent status and is not meant to be — the list describes who is currently building GatherPress, and it stays honest by changing. And the leads' judgement is genuinely part of it, particularly for contribution that happens outside the repository. We would rather say that plainly than dress it up as a formula.

If you want to be in that group, the route is the same one everyone took: pick up issues, review other people's work, and keep showing up. We would much rather grow this list than prune it.

---

Thanks for helping make GatherPress better! 💜
