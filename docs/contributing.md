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

---

Thanks for helping make GatherPress better! 💜
