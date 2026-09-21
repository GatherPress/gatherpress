# Contributing to GatherPress

Everything you need to work on GatherPress itself: the environment, the test suites, the branch model, and the ways to help that involve no code at all.

If you are building *with* GatherPress rather than *on* it, you want the [developer docs](../developer/README.md) instead.

## Before anything else

Read the [Code of Conduct](../../CODE_OF_CONDUCT.md). It applies in the repository, in Slack, and at events, and it is the one thing here that is not optional.

## Finding something to work on

Every issue is filed against a **release milestone**, so the fastest way to contribute something that lands soon is to work from the [milestone we are currently focused on](https://github.com/GatherPress/gatherpress/milestones) rather than from the full issue list. Anything outside the current milestone may be a while off, however good it is.

Once something catches your eye, **read the activity on it before you start**. Check whether it is already assigned, whether somebody has left comments that change the shape of it, and whether a pull request is already open. That is a minute of reading that saves you from rebuilding work somebody has already done.

If it is unclaimed and you think you can finish it in a reasonable amount of time, **say so in a comment**. A maintainer will assign it to you, which tells everyone else it is taken.

You do not have to write code to be useful on an issue. Commenting on an idea, pointing out a case nobody considered, or reviewing an open pull request all move an issue forward, and all of it is welcome.

Stuck, or not sure whether something is a good first issue? Ask. The maintainers and other contributors are in Slack:

- **[#gatherpress in the Make WordPress Slack](https://wordpress.slack.com/archives/C07NB4N0ESJ)**, which is open to anyone with a WordPress.org account. Start here.
- **[The GatherPress Slack](https://join.slack.com/t/gatherpress/shared_invite/zt-2luaqcruf-iQm_o2UuKBpnX7zfRCxMAg)**, where the day-to-day work happens, including a weekly huddle. Ask if you would like to join.

## Contributing code

### Set up

[Developer docs → Local Development](../developer/README.md) covers forking, cloning, installing dependencies and starting `wp-env`. The short version:

```bash
git clone git@github.com:YourGitHubUsername/gatherpress.git
cd gatherpress
npm install
composer install
npm run build
npm run wp-env start
```

### Branches and pull requests

- **`develop` is the trunk.** Branch from it, and target it with your pull request. `main` reflects what has been released and only receives release-train PRs.
- **Pull requests into `develop` are squash-merged.** Branch protection enforces linear history and signed commits, so one tidy commit is what lands however many you pushed.
- **Fixes are born on `develop`**, even when they are destined for a patch release. Patch branches receive them as cherry-picks.
- **Every pull request needs changelog handling**: either a file in `.github/changelog/` (`composer changelog:add` writes one) or the `Skip Changelog` label for changes nobody would read about, such as CI tweaks or docs.

The full picture, including how releases are cut, is in the [release process](release-process.md).

### Conventions

`AGENTS.md` in the repository root is the house style: US English, WordPress Coding Standards, `@since TBD` on anything new, and a long list of patterns this codebase has settled on. It is written for AI coding agents and is just as useful to people.

Run the linters before you push, because CI will:

```bash
npm run lint:php
npm run lint:js
npm run lint:css
npm run lint:md:docs
```

### Tests

| Suite | Command | Guide |
|---|---|---|
| PHP unit | `npm run test:unit:php` | [unit-tests](unit-tests/README.md) |
| PHP unit, multisite | `npm run test:unit:php:multisite` | [unit-tests](unit-tests/README.md) |
| JavaScript unit | `npm run test:unit:js` | none |
| End-to-end | `npm run test:e2e` | [e2e-tests](e2e-tests/README.md) |

New code is expected to be covered on every branch, not just the happy path. `AGENTS.md` is specific about what that means.

### Tooling

- [Screenshot generator](screenshot-generator/README.md): regenerates the WordPress.org screenshots, in every locale
- [Playground PR previews](playground-pr-preview/README.md): the live preview attached to each pull request
- [Release process](release-process.md): the version bump, the credits script, and the whole release train

## Contributing without writing code

Code is one contribution among several, and the others are genuinely short-handed.

### Test a pull request

Every pull request gets a Playground preview link: a real WordPress with the branch installed, seeded with demo data, that you can click through in a browser tab. No checkout, no local setup.

Open one, try the thing the PR claims to fix, and say what happened. "Works on my phone too" and "the modal closes but the count does not update" are both more valuable than silence. [Playground PR previews](playground-pr-preview/README.md) explains how to shape the preview when a PR needs a particular setup.

### Triage issues

New issues need someone to reproduce them, add the version and environment, and say whether they are still happening. A confirmed bug with clear steps is most of the way to a fix; an unconfirmed report is a question nobody can act on.

Browse [open issues](https://github.com/GatherPress/gatherpress/issues) and start with the ones nobody has answered, or work through the [current milestone](https://github.com/GatherPress/gatherpress/milestones) if you would rather help with what is shipping next.

### Translate

GatherPress is translated on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/gatherpress/). Suggestions are open to anyone with a WordPress.org account, and locale teams review them.

Strings for a release reach translators when its beta is tagged, so translating early in a cycle means the release ships with your locale complete.

### Add to the demo data

The demo content behind every Playground preview and screenshot lives in [gatherpress-demo-data](https://github.com/GatherPress/gatherpress-demo-data). Real meetups make better demos than filler, so adding your own events is a contribution to every preview anyone opens.

### Talk about it

Write about how your group uses it, answer a question in the [support forum](https://wordpress.org/support/plugin/gatherpress/), post a screenshot of your events page. Most people find GatherPress because somebody they follow mentioned it.

### Show up

- **[#gatherpress in the Make WordPress Slack](https://wordpress.slack.com/archives/C07NB4N0ESJ)**: open to anyone with a WordPress.org account
- **[GatherPress Slack](https://join.slack.com/t/gatherpress/shared_invite/zt-2luaqcruf-iQm_o2UuKBpnX7zfRCxMAg)**: where the day-to-day work is discussed, including a weekly huddle. Ask if you would like to join
- **[gatherpress.org/get-involved](https://gatherpress.org/get-involved)**: the current list of ways to help

## Credits and access

Contributors are credited by their **WordPress.org username**, on the in-plugin Credits screen and on WordPress.org. Link your GitHub account on your [profiles.wordpress.org](https://profiles.wordpress.org/) profile and the automation resolves you without anyone having to do it by hand.

[Contributing](../contributing.md) covers how credits are grouped, how someone moves between groups, and who to ask for repository or infrastructure access.
