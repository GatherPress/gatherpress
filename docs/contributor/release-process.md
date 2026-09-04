# Release Process

This page is the single source of truth for release managers cutting a stable
release, a patch release, or a pre-release of GatherPress. The tag-driven
mechanics are automated by
[`.github/workflows/release.yml`](../../.github/workflows/release.yml); this
doc covers the full release train around them — what to do before the tag,
what the tag automates, and what must happen after — based on how the
0.34.0 release actually shipped. Automation of the remaining manual steps is
tracked in [#1921](https://github.com/GatherPress/gatherpress/issues/1921).

## Branch model

- **`develop`** is the trunk. All feature and fix PRs target it, every PR is
  **squash-merged** (branch protection enforces linear history and signed
  commits), and every PR carries either a `.github/changelog/` entry file or
  the `Skip Changelog` label.
- **`main`** reflects the released state. Only release-train PRs target it:
  the develop→main release merge, patch release branches, and changelog
  parity syncs. Release merges into main use **merge commits — never
  squash** a develop→main PR (squashing 1,000+ commits into one guarantees
  the branches permanently diverge). If main's protection has
  "Require linear history" enabled, it must be unchecked for the release
  merge to land.
- **Fixes are born on `develop`.** Patch releases receive them as
  cherry-picks (see [Patch release flow](#patch-release-flow)); nothing
  original should be written on a patch branch unless the bug doesn't exist
  on develop.
- **GatherPress Alpha is versioned in lockstep.** It refuses to run when its
  version differs from core's, so every core release (stable or patch) needs
  a matching version sync and release in
  [gatherpress-alpha](https://github.com/GatherPress/gatherpress-alpha).
  Alpha mirrors this branch model for exactly that reason: its `develop`
  carries whatever core's `develop` carries and is where pre-releases are
  tagged, its `main` is the released state. The pairing is one rule — core
  develop with alpha develop, core main with alpha main — and because the
  version check is exact string equality, mixing them across branches makes
  Alpha inert.

## What gets automated

Pushing a tag of the form `X.Y.Z` (stable) or `X.Y.Z-alpha.N` / `-beta.N` /
`-rc.N` (pre-release) triggers `release.yml`. The workflow:

| Tag pattern             | Distro zip                  | GitHub Release entry | Changelog body source                       | wp.org deploy |
| ----------------------- | --------------------------- | -------------------- | ------------------------------------------- | ------------- |
| `0.34.0`                | `gatherpress.0.34.0.zip`    | Release (latest)     | Rolled-up `[0.34.0]` section, committed back to `CHANGELOG.md` via auto-PR | Yes |
| `0.34.0-alpha.1`        | `gatherpress.0.34.0-alpha.1.zip` | Pre-Release    | Rolled-up `[0.34.0-alpha.1]` section computed in an ephemeral checkout (no commit) | No |
| `0.34.0-beta.1` / `-rc.1` | Same shape as alpha       | Pre-Release          | Same shape as alpha                         | No            |

The distro zip's outer filename carries the version; the inner layout is
always `gatherpress/...` so it installs cleanly under the right slug.
Stable tags are cut from `main`; pre-release tags are cut from `develop`.

## Version bump generation

Version bumps are never hand-edited. The generator lives in-repo at
`.github/scripts/release/generate-version.php`
([#1827](https://github.com/GatherPress/gatherpress/issues/1827) retired the
old `wp gatherpress develop generate_version` WP-CLI command in the
[gatherpress-develop](https://github.com/GatherPress/gatherpress-develop)
plugin). Run it via the **Version Bump** workflow (Actions → Version Bump →
enter the version), or locally:

```bash
npm run version:bump -- --version=X.Y.Z
npm i --package-lock-only
```

It reads the target version's credits file at
`.github/scripts/release/credits/X.Y.Z.json` (hand-added first via a normal
PR; new contributors accumulate in `credits/unreleased.json` and fold in
automatically at bump time), then writes:

- `gatherpress.php` `Version:` header, `package.json` version, `readme.txt`
  `Stable tag:`, the version badge in `README.md`, and the regenerated
  `includes/data/credits.php`.
- The GatherPress Alpha `Version:` header in the sibling
  `../gatherpress-alpha` checkout, when present — the Version Bump workflow
  runs with no sibling checkout and skips it, so alpha's version PR is opened
  separately (see the release-train PR list below).
- `SECURITY.md` supported-versions tables in both plugins.

`README.md`, `readme.txt`, `includes/data/credits.php`, and `SECURITY.md` are
generated output — never edit them by hand. The `npm i --package-lock-only`
step above refreshes the lockfile to match the new `package.json` version.

## Pre-release flow

**Use case.** You want testers to be able to download a build of the
in-progress release and see what's queued for it, without touching wp.org.

**Prepare the version PRs** (one release train, three PRs, in merge order):

1. **Credits file** → this repo's
   `.github/scripts/release/credits/X.Y.Z-suffix.N.json`, added via a normal
   PR to `develop`. Usually the leads/team roster copied forward; new
   contributors accumulate in `credits/unreleased.json` and the bump folds
   them in automatically.

   **On the cycle's first alpha**, copy forward from the `-alpha.0` marker
   file and delete it in the same PR. The marker only exists between one
   release and the next, so it should not outlive the cycle it opened.
   Copy before deleting, in that order: the alpha.0 bump folded
   `credits/unreleased.json` into that file and emptied it, so deleting
   first drops every contributor who landed work during the window.
2. `version-X.Y.Z-suffix.N` → gatherpress-alpha `develop`: the synced version
   header (`Skip Changelog` label). Opened by that repo's **Version Bump**
   workflow — core's Version Bump workflow dispatches it automatically when
   the `GATHERPRESS_ALPHA_TOKEN` secret is configured, and otherwise prints
   the `gh workflow run` one-liner in its run summary.
3. `version-X.Y.Z-suffix.N` → core `develop`: the generated bump
   (`Skip Changelog` label). Opened by core's **Version Bump** workflow.

After the core release workflow finishes, its `alpha-handoff` job likewise
dispatches (or documents, without the token) the matching gatherpress-alpha
release — alpha's workflow refuses to cut a release until its version PR has
merged, so triggering it early fails loudly rather than shipping a mismatch.

**Cut it:**

```bash
git checkout develop
git pull origin develop
git tag 0.34.0-alpha.1
git push origin 0.34.0-alpha.1
```

**What the workflow does:**

1. Detects the tag is a pre-release (the `-alpha.` / `-beta.` / `-rc.` suffix).
2. Builds `gatherpress.0.34.0-alpha.1.zip` via `npm run plugin-zip`.
3. Runs the changelog rollup in an ephemeral working copy and extracts the resulting `[0.34.0-alpha.1]` section as the release body. The changes never get committed anywhere — they evaporate when the job ends.
4. Creates a GitHub **Pre-Release** with the zip attached and the rolled-up body. The Pre-Release is **not** marked as the latest release.
5. **`.github/changelog/*` entries are left in place** in the repository so the eventual stable release still has them.
6. Skips the wp.org deploy entirely.

Testers downloading the pre-release zip see the same changelog body they'd see at stable release time — minus any further entries that land between now and then.

**Verify after the workflow lands:**

- [GitHub Releases page](https://github.com/GatherPress/gatherpress/releases) shows the new tag with a "Pre-release" badge.
- The release body matches the queued `.github/changelog/` entries.
- PR numbers in the release body are **links**, not literal `[#1234]` text. See the troubleshooting entry below if they are bare.
- The attached zip downloads as `gatherpress.X.Y.Z-alpha.N.zip` and unzips with a `gatherpress/` top-level directory.
- wp.org listing at <https://wordpress.org/plugins/gatherpress/> is **unchanged** — the served version comes from trunk's `Stable tag:`, which the trunk sync deliberately preserves.
- For **beta** tags only: SVN trunk is synced to the beta so [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/gatherpress/) picks up new strings in its Development project (check they appear there), and a matching SVN tag is created so the beta shows as a named download in the plugin page's [Advanced view](https://wordpress.org/plugins/gatherpress/advanced/) — the new tag may need a release-confirmation click first.
- Tag and release GatherPress Alpha at the same version (its own tag-driven `release.yml`, GitHub-only).

## Stable release flow

**Use case.** Cutting a real release that ships to end users via wp.org.

### 1. Pre-flight

- [ ] All issues in the release's milestone are closed or moved.
- [ ] Queued `.github/changelog/` entries read correctly (skim, fix anything off).
- [ ] Rehearse the rollup in a throwaway tree so the tag run cannot fail on the changelog: `git worktree add /tmp/rollup main && cd /tmp/rollup && ../<repo>/vendor/bin/changelogger write --use-version=X.Y.Z --yes`, then check `CHANGELOG.md` has the new `## [X.Y.Z]` section and its `[X.Y.Z]:` link, and discard the worktree. This catches a missing link line under the previous version's heading, which the parser refuses (see Troubleshooting).
- [ ] Credits file for `X.Y.Z` added at `.github/scripts/release/credits/X.Y.Z.json` — by convention a copy of the latest pre-release file, roster corrections applied to the pre-release file first.
- [ ] `npm run version:bump -- --version=X.Y.Z` (or the Version Bump workflow) + `npm i --package-lock-only`; version PRs opened and merged in order: credits file, alpha sync, core `version-X.Y.Z` → develop.

### 2. Release merge (develop → main)

`main` lags develop by a whole cycle and carries release-line commits of its
own, so this is a real merge, not a fast-forward:

```bash
git checkout develop && git pull origin develop
git checkout -b version-X.Y.Z          # reuse the name after its develop PR merged
git merge origin/main                  # expect conflicts; resolve ALL in develop's favor
git diff origin/develop                # MUST be empty — tree identical to develop
git push -u origin version-X.Y.Z
gh pr create --base main --title "Release X.Y.Z"
```

Expected conflicts are version strings (`gatherpress.php`, `package.json`,
`package-lock.json`, `includes/data/credits.php`) and generated files —
always take develop's side. The empty-diff check is the safety net: the
release ships exactly what develop has.

**Merge the PR with a merge commit** (toggle "Require linear history" off on
main first if enabled). Never squash it.

### 3. Tag it

```bash
git checkout main && git pull origin main
git tag X.Y.Z
git push origin X.Y.Z
```

**What the workflow does:**

1. Detects the tag is stable (no `-alpha.` / `-beta.` / `-rc.` suffix).
2. Aggregates every entry file in `.github/changelog/` into a new `## [X.Y.Z] - YYYY-MM-DD` section at the top of `CHANGELOG.md`, appending `[#NNNN]` PR references, and **deletes** the entry files (dotfiles like `.gitkeep` survive, which is why the directory persists in git).
3. Commits that rollup to a new `release/X.Y.Z` branch and **opens an auto-PR back to `develop`** (with the `Skip Changelog` label).
4. Builds `gatherpress.X.Y.Z.zip` with the rolled-up `CHANGELOG.md` from step 2 in it. The tag commit's own copy predates the rollup, so the build and the wp.org deploy take the rolled file from the rollup job rather than the checkout; before this, every shipped changelog was one release behind.
5. Creates a GitHub **Release** with the zip attached, marked as **latest**, with the `[X.Y.Z]` section as the body.
6. Deploys to wordpress.org via the `10up/action-wordpress-plugin-deploy` action using the `SVN_USERNAME` / `SVN_PASSWORD` secrets.

### 4. After the tag — closing the loop

Every one of these is required; skipping any of them bites the next release:

- [ ] **Confirm the wp.org release.** wp.org emails committers a release
  confirmation link; the new version is not live in the plugin directory
  until a committer clicks it.
- [ ] **Confirm the `release/X.Y.Z` rollup PR auto-merged into develop.**
  The workflow creates its commit via the API (GitHub-signed) and enables
  auto-merge (squash), so it lands on its own once checks pass — this brings
  the rolled-up `CHANGELOG.md` to develop and removes the consumed entry
  files. If it's still open, see Troubleshooting.

  Know what that PR is before you touch it: the workflow branches
  `release/X.Y.Z` **off the tag commit on `main`**, so GitHub's diff view
  shows everything `main` gained since the last release merge, often
  dozens of commits and a hundred files. Almost all of it is
  content-identical to what `develop` already has. The only real change is
  the rollup commit itself: the new section, its link line, and the deleted
  entry files. If the PR is `DIRTY`, do **not** resolve it by merging
  `main`'s state into `develop`; see "The rollup PR is DIRTY" below.
- [ ] **Check `CHANGELOG.md` on both branches has exactly one `## [X.Y.Z]`
  heading and a matching `[X.Y.Z]:` link line**, and that `main` has no
  duplicate headings. A re-run of the tag after the entries were consumed
  writes a second, empty heading (see "I need to move the tag" below).
- [ ] **Check the PR numbers in the new changelog section are links.**
  `changelogger --add-pr-num` only emits bare `[#1234]` markers; the rollup
  job runs `.github/scripts/link-changelog-prs.php` immediately afterward to
  turn them into inline links. Bare markers mean that step did not run — see
  Troubleshooting.
- [ ] **Sync the rollup state to main** (changelog parity): the auto-PR only
  targets develop, so cherry-pick develop's rollup squash commit onto a
  branch off main and PR it (`Sync X.Y.Z changelog rollup state to main`).
  Without this, the next patch release cut from main re-rolls every
  already-released entry into its changelog.
- [ ] **Release GatherPress Alpha**: merge its `version-X.Y.Z` sync PR if
  not already done, **and make sure alpha has a changelog entry file for
  `X.Y.Z` on the branch it releases from** (`.github/changelog/sync-X-Y-Z`,
  "Track the GatherPress X.Y.Z release, which the plugin is version-locked
  to."). Alpha's rollup has nothing to write without it and its release
  fails at "Extract release body" before tagging; 0.35.3 needed a second
  PR for exactly this. Then let the core release workflow's `alpha-handoff` job
  dispatch alpha's release (automatic with the `GATHERPRESS_ALPHA_TOKEN`
  secret; otherwise run the `gh workflow run release.yml` one-liner from the
  job summary — a manual `git tag X.Y.Z && git push origin X.Y.Z` on alpha
  still works too, tagged on the branch that version lives on: `main` for a
  stable, `develop` for a pre-release). Alpha's release train mirrors core's,
  so its stable releases also want a develop→main merge before the tag.
  Merge alpha's own rollup PR afterward, then do alpha's **changelog parity**
  too: since alpha split `develop` from `main` (alpha #74) its rollup lands
  on one branch only, so cherry-pick that squash across to the other, the
  same way as core's parity step above. Alpha's `main` also runs whatever
  copy of the workflow it has, which lags `develop` until alpha's next
  release merge; if a dispatch from the Actions tab builds the wrong
  branch, dispatch with `--ref main` (alpha #79 makes the checkout follow
  the version instead).
- [ ] **Bring the demo data in line with the new version**: follow the
  "Preparing demo-data for a new version of GatherPress" steps in the
  [gatherpress-demo-data README](https://github.com/GatherPress/gatherpress-demo-data#readme)
  so the Playground demo content matches the release.
- [ ] **Open the next cycle** (minor and major releases only, not patches):
  bump develop to `X.Y+1.0-alpha.0` via the **Version Bump** workflow and
  merge its PR. Add the `credits/X.Y+1.0-alpha.0.json` file first — the
  workflow refuses a version with no credits file — seeded from the most
  recent release's file, which for a line that took patches means the
  `X.Y.N` file that landed on main rather than the `X.Y.0` one on develop.

  This is a marker, not a release. Nothing is tagged, and only tags deploy,
  so merging it ships nothing. What it buys is that develop stops
  advertising the version that just went out: before this step develop's
  headers and README badge still read `X.Y.0` while the branch holds
  `X.Y+1` work, which is what
  [#2166](https://github.com/GatherPress/gatherpress/issues/2166) reported.

  The marker lives only until the cycle's first real alpha: `-alpha.1`
  copies its credits file forward and deletes it, per the pre-release flow.
  Alpha's Version Bump is dispatched by core's, so its `develop` picks up
  the same marker automatically; both repos' release workflows refuse to
  build anything from an `-alpha.0` tag.

  Known gap: the generator also moves `SECURITY.md`'s supported-versions
  table to `X.Y+1.x`, which overstates support while `X.Y.N` is the released
  line. Correct that row by hand until `patch_security_table()` learns to
  skip pre-release versions.
- [ ] **Delete the spent branches**: `version-X.Y.Z` (core and alpha) and the
  credits-file PR branch. The `release/X.Y.Z` branches go when their PRs merge.

**Verify:**

- [GitHub Releases page](https://github.com/GatherPress/gatherpress/releases) shows the new tag as "Latest release" with the zip attached.
- The shipped changelog is the rolled one: `unzip -p gatherpress.X.Y.Z.zip gatherpress/CHANGELOG.md | head -20` opens with `## [X.Y.Z]`, and so does <https://plugins.svn.wordpress.org/gatherpress/tags/X.Y.Z/CHANGELOG.md>. Every release before 0.35.3 shipped the previous version's changelog here, because the build and deploy jobs checked out the tag commit while the rollup wrote the section in its own job; the workflow now hands the rolled file to both. If a shipped copy is wrong anyway, see "The shipped CHANGELOG.md is one release behind" below.
- wp.org listing at <https://wordpress.org/plugins/gatherpress/> shows the new version (after the confirmation click; the directory can lag a few minutes).
- `develop` and `main` both have the `[X.Y.Z]` CHANGELOG section and an empty `.github/changelog/`.

## Patch release flow

**Use case.** Shipping `X.Y.1` for the released line while develop has moved
on to the next minor.

1. **Fixes land on develop first** via normal PRs (with changelog entry
   files), milestoned `X.Y.1`. Only fix directly on the patch branch when
   the bug doesn't exist on develop anymore.
2. **Cut the patch branch from main** — this is why the changelog parity
   step above matters; main must start with a clean `.github/changelog/`:

    ```bash
    git checkout main && git pull origin main
    git checkout -b version-X.Y.1
    git cherry-pick -x <sha-from-develop> [...]
    ```

    The `-x` records the source commit; the cherry-picks bring their
    changelog entry files along.
3. **Version bump on the patch branch**: add the `X.Y.1` credits file (copy
   the `X.Y.0` file forward, appending any new contributors) +
   `npm run version:bump -- --version=X.Y.1` + lockfile refresh.
4. **PR `version-X.Y.1` → main, merge, tag `X.Y.1` on main.** The workflow
   ships it exactly like a stable release (it is one), and opens
   `release/X.Y.1` → develop.
5. **Close the loop** exactly as in the stable flow: merge the rollup PR
   into develop promptly — because the fixes originated on develop, the
   rollup deletes those same entry files there, so the next minor's
   changelog won't re-list them — then parity-sync main, release alpha at
   `X.Y.1`, delete spent branches. Skip the "open the next cycle" step:
   develop is already on `X.Y+1.0-alpha.N` and a patch doesn't start a new
   line.

Because every patch commit on main is a content-identical cherry-pick of a
develop commit, the next minor's develop→main release merge auto-resolves
everything except the usual version-string conflicts.

## Troubleshooting

### "Rolled-up CHANGELOG.md does not contain a `[X.Y.Z]` section"

The rollup job failed. Almost always means `.github/changelog/`
was empty at tag time. Confirm by checking out the tag locally and looking:

```bash
git checkout 0.34.0
ls .github/changelog/
```

If it's truly empty, the release shouldn't go out — there's nothing to ship.
If there are entries but the rollup still failed, run the same command
locally to reproduce:

```bash
vendor/bin/changelogger write \
  --use-version=0.34.0 \
  --release-date="$(date +%Y-%m-%d)" \
  --add-pr-num \
  --deduplicate=-1 \
  --yes
```

### "Heading seems to have a linked version, but link was not found"

The rollup job fails before anything ships, with changelogger naming the
previous release's heading (`## [X.Y.Z-1] - ...`). That heading has no
matching `[X.Y.Z-1]: https://github.com/GatherPress/gatherpress/compare/...`
line at the bottom of `CHANGELOG.md`; changelogger validates the heading it
is about to push down, so a rollup that wrote a heading without its link
only fails on the *next* release. The 0.35.2 rollup did this and 0.35.3's
first tag run caught it.

Add the missing link line on `main` (PR, merge commit) and on `develop`
(PR, squash) so parity holds, then delete and re-push the tag. The
pre-flight rehearsal above catches it before tagging.

### The changelog contains entries from the previous release

The tag was cut from a `main` that never got the previous release's parity
sync, so already-released entry files were still present and got rolled up
again. Fix main first (cherry-pick the previous rollup commit onto main),
delete the bad tag and GitHub Release, and re-tag.

### PR numbers in the changelog render as literal `[#1234]` text

`changelogger write --add-pr-num` appends bare markers and never links them.
`.github/scripts/link-changelog-prs.php` rewrites each one as an inline link
and runs as the last part of the rollup job, and again as the last step of
`composer changelog:write`. Inline links (rather than reference-style
definitions at the bottom of the file) are deliberate: the workflow extracts
a version's section out of `CHANGELOG.md` to use as the GitHub Release body,
and a definition living elsewhere in the file would not survive that.

Bare markers mean the script did not run. Repair the committed file by
running `php .github/scripts/link-changelog-prs.php` on develop and PRing the
result — it is idempotent, so it only touches unlinked markers — then check
that the workflow still calls it. This exact gap shipped in 0.34.0 and
0.34.1: the script was added in #1900 and wired into `composer
changelog:write`, but the rollup job calls `vendor/bin/changelogger` directly
and was never updated to match (#2035).

### The release merge PR can't be merged with a merge commit

`main`'s branch protection has "Require linear history" enabled — GitHub
reports "Merge commits are not allowed on this repository" even when the
repo-level merge settings allow them, and a direct push of a merge commit is
rejected with "This branch must not contain merge commits." Uncheck
*Require linear history* in the `main` protection rule for the merge, and
re-enable afterward if that's the standing policy.

### The rollup PR is DIRTY (or shows hundreds of files)

`release/X.Y.Z` is branched off the tag commit, so it carries `main`'s whole
state. That merged cleanly while `develop` had not moved past the release,
but once the next cycle is open (`X.Y+1.0-alpha.0` on develop) the version
strings conflict and the PR goes `DIRTY`, and auto-merge cannot fire.

Rebuild the branch as `develop` plus only the rollup commit, and let
auto-merge take it from there:

```bash
git fetch origin
git checkout -B release/X.Y.Z origin/develop
git cherry-pick -x <rollup commit sha>   # "Roll up changelog for X.Y.Z" on release/X.Y.Z
git diff --stat origin/develop..HEAD      # must be CHANGELOG.md + deleted entry files, nothing else
git push --force-with-lease origin release/X.Y.Z
```

Never resolve it by merging `main` into `develop` through this PR: that would
carry the release's version strings, credits, and rollups onto `develop`.

### I need to move the tag after the rollup ran

Re-pushing `X.Y.Z` re-runs the whole workflow with the entry files already
consumed. changelogger then writes a second, empty `## [X.Y.Z]` heading and
the rollup job opens another `release/X.Y.Z` PR whose only real change is
that heading. Close that PR and delete its branch (nothing was consumed, so
the double-roll guard is satisfied). The zip is rebuilt from the new tag
commit, which is the point of moving it; the wp.org deploy sees
`tags/X.Y.Z` already exists and bails without touching SVN; the alpha
handoff dispatches again. Move the tag only after the parity cherry-pick,
so the new tree carries its own section.

### The shipped CHANGELOG.md is one release behind

The zip and the wp.org tag carried the tag commit's `CHANGELOG.md`, which
predates the rollup, on every release up to 0.35.3. The workflow now ships
the rolled file. If it happens anyway, fix the copies that shipped:

- **wp.org**: commit the rolled file into both the tag and trunk. Sparse
  checkouts keep it to one file each:

  ```bash
  svn checkout --depth empty https://plugins.svn.wordpress.org/gatherpress/tags/X.Y.Z t && svn update t/CHANGELOG.md
  svn checkout --depth empty https://plugins.svn.wordpress.org/gatherpress/trunk trunk && svn update trunk/CHANGELOG.md
  git show main:CHANGELOG.md > t/CHANGELOG.md && git show main:CHANGELOG.md > trunk/CHANGELOG.md
  svn diff t trunk        # must be the new section and its link line, nothing else
  svn commit --username gatherpress -m "Add the X.Y.Z changelog section" t/CHANGELOG.md trunk/CHANGELOG.md
  ```

  Do this before clicking the wp.org release confirmation and nobody
  downloads the stale copy.
- **GitHub Release asset**: replace only `CHANGELOG.md` *inside CI's own
  zip*, never a local rebuild (compiled chunk hashes differ from CI's), then
  `gh release upload X.Y.Z gatherpress.X.Y.Z.zip --clobber` and confirm
  every other entry's hash is unchanged. Re-tagging after parity has the
  same effect through the workflow, with the caveats in the entry above.

### The rollup auto-PR didn't merge on its own

The workflow signs its commit via the API and enables auto-merge, so the PR
normally lands once checks pass. If it's stuck, merge it manually with
squash (`gh pr merge --squash`, `--admin` if protection complains). Do not
close the PR: unmerged, it leaves consumed entry files on develop and the
next release double-rolls them — the release workflow now refuses to run a
stable tag while a `release/*` PR is open, so an ignored rollup PR blocks
the next release rather than corrupting its changelog.

### wp.org deploy failed

Usually means the `SVN_USERNAME` / `SVN_PASSWORD` secrets are stale or the
SVN repo state diverged. Quickest recovery:

1. Manually run `npm run plugin-zip` locally on the tag's commit.
2. Upload the zip to <https://wordpress.org/plugins/developers/add/> as the new version, or use `svn` to push it through the wp.org SVN flow manually.
3. File an issue to rotate the secrets if that's the root cause.

The GitHub Release stays correct regardless — it's already attached the zip
and the release body. Note that if the SVN tag already exists, the deploy
action **exits successfully without pushing anything** ("Version X.Y.Z ...
was already published") — a re-run after a partial deploy silently no-ops
with a green check. Clean up the bad SVN tag manually before re-running.

### Auto-PR for the changelog rollup didn't open

The rollup job failed late (after the rollup commit pushed but
before `gh pr create` ran). Check the job log; if the branch
`release/X.Y.Z` exists on `origin` with the rollup commit, open the PR
manually:

```bash
gh pr create \
  --base develop \
  --head release/0.34.0 \
  --title "Roll up changelog for 0.34.0" \
  --body "Automated rollup of .github/changelog/* entries." \
  --label "Skip Changelog"
```

### I tagged the wrong commit

Delete the tag locally and on origin, then re-tag:

```bash
git tag -d 0.34.0
git push --delete origin 0.34.0
# fix up the branch, then re-tag
git tag 0.34.0
git push origin 0.34.0
```

If the release workflow already ran against the bad tag, also:

- Delete the GitHub Release entry (it'll auto-recreate on the new tag push).
- Close the bogus auto-PR and delete its `release/X.Y.Z` branch.
- Revert the wp.org deploy if it shipped — contact wp.org plugin team if you can't.

## Secrets and permissions required

The workflow uses these secrets from the repo / org settings:

- `SVN_USERNAME` — wp.org SVN username (used by the wp.org deploy step only).
- `SVN_PASSWORD` — wp.org SVN password (used by the wp.org deploy step only).
- `GITHUB_TOKEN` — auto-provisioned; the workflow declares least-privilege scopes per job (`contents: write` for the rollup commit + Release creation, `pull-requests: write` for the auto-PR).

If the `permissions:` block on `release.yml` is ever loosened, double-check
that no job ends up with broader scopes than it needs.

## Versioning conventions

- **Stable**: `0.34.0`, `0.35.0`, `1.0.0`. Three numeric components, no suffix. These ship to wp.org and are tagged on `main`.
- **Patch**: `0.34.1`. Same shape as stable; cut from a `version-X.Y.1` branch off `main` per the patch flow.
- **Cycle marker**: `0.36.0-alpha.0`. Never tagged, never released. Set on `develop` immediately after a stable ships so the branch names the line being worked on instead of the one that just went out, and retired when `-alpha.1` supersedes it. The `.0` is what distinguishes it from a real build.
- **Alpha**: `0.34.0-alpha.1`, `0.34.0-alpha.2`. Use for early in-cycle builds; tagged on `develop`. Numbering starts at `.1` because `.0` is the cycle marker above.
- **Beta**: `0.34.0-beta.1`. Use for feature-complete in-cycle builds where the team is still smoke-testing; tagged on `develop`.
- **Release candidate**: `0.34.0-rc.1`. Use for "we believe this is shippable, last call for showstoppers"; tagged on `develop`.

The pre-release suffix matches the SemVer spec — anything outside `-alpha.` / `-beta.` / `-rc.` won't be recognized by the workflow's classifier and will be treated as stable. Don't get creative with the suffix.
