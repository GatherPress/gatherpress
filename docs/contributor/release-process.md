# Release Process

This page is the single source of truth for release managers cutting a stable
release or a pre-release of GatherPress. The mechanics are automated by
[`.github/workflows/release.yml`](../../.github/workflows/release.yml); this
doc covers what to expect, what to verify, and what to do when something
doesn't go as planned.

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

## Pre-release flow

**Use case.** You want testers to be able to download a build of the
in-progress release and see what's queued for it, without touching wp.org.

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
3. Runs `composer changelog:write --use-version=0.34.0-alpha.1 ...` in an ephemeral working copy and extracts the resulting `[0.34.0-alpha.1]` section as the release body. The changes never get committed anywhere — they evaporate when the job ends.
4. Creates a GitHub **Pre-Release** with the zip attached and the rolled-up body. The Pre-Release is **not** marked as the latest release.
5. **`.github/changelog/*` entries are left in place** in the repository so the eventual stable release still has them.
6. Skips the wp.org deploy entirely.

Testers downloading the pre-release zip see the same changelog body they'd see at stable release time — minus any further entries that land between now and then.

**Verify after the workflow lands:**

- [GitHub Releases page](https://github.com/GatherPress/gatherpress/releases) shows the new tag with a "Pre-release" badge.
- The release body matches what's currently in `CHANGELOG.md`'s `[Unreleased]` section.
- The attached zip downloads as `gatherpress.X.Y.Z-alpha.N.zip` and unzips with a `gatherpress/` top-level directory.
- wp.org listing at <https://wordpress.org/plugins/gatherpress/> is **unchanged**.

## Stable release flow

**Use case.** Cutting a real release that ships to end users via wp.org.

**Pre-flight checklist:**

- [ ] All issues in the release's milestone are closed or moved.
- [ ] `[Unreleased]` content in `CHANGELOG.md` reads correctly (skim it, fix anything off).
- [ ] Version is bumped consistently across:
    - `gatherpress.php` (the `Version:` header)
    - `package.json` (the `version` field)
    - `readme.txt` (the `Stable tag:` line)
- [ ] All current `feature/*` branches that should be in the release are merged to `develop`.

**Cut it:**

```bash
git checkout develop
git pull origin develop
git tag 0.34.0
git push origin 0.34.0
```

**What the workflow does:**

1. Detects the tag is stable (no `-alpha.` / `-beta.` / `-rc.` suffix).
2. Runs `composer changelog:write --use-version=0.34.0 --release-date=<today> --add-pr-num --deduplicate=-1 --yes`. This:
    - Aggregates every file in `.github/changelog/` into a new `## [0.34.0] - YYYY-MM-DD` section at the top of `CHANGELOG.md`.
    - Appends `[#NNNN]` to each entry from the originating PR's merge commit subject.
    - **Deletes** the entry files so the next cycle starts clean.
3. Commits the rolled-up `CHANGELOG.md` + the deleted entry files to a new `release/0.34.0` branch and **opens an auto-PR back to `develop`** for the release manager to merge after the release ships. The auto-PR carries the `Skip Changelog` label so the changelog gate from #1690 doesn't block it.
4. Builds `gatherpress.0.34.0.zip`.
5. Extracts the newly-written `[0.34.0]` section from `CHANGELOG.md` as the release body.
6. Creates a GitHub **Release** with the zip attached, marked as **latest**.
7. Deploys to wordpress.org via the `10up/action-wordpress-plugin-deploy` action using the `SVN_USERNAME` / `SVN_PASSWORD` secrets.

**Verify after the workflow lands:**

- [GitHub Releases page](https://github.com/GatherPress/gatherpress/releases) shows the new tag as "Latest release".
- The release body matches the `[0.34.0]` section that just landed in `CHANGELOG.md` on the `release/0.34.0` branch.
- The auto-PR titled "Roll up changelog for 0.34.0" is open against `develop`. **Merge this once you've confirmed the release body renders correctly** — that returns the rolled-up `CHANGELOG.md` and the cleaned `.github/changelog/` to the long-lived branch.
- The attached zip downloads as `gatherpress.0.34.0.zip` and unzips with a `gatherpress/` top-level directory.
- wp.org listing at <https://wordpress.org/plugins/gatherpress/> shows the new version within a few minutes of the deploy.

## Troubleshooting

### "Rolled-up CHANGELOG.md does not contain a `[X.Y.Z]` section"

The `rollup-changelog` job failed. Almost always means `.github/changelog/`
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

### wp.org deploy failed

Usually means the `SVN_USERNAME` / `SVN_PASSWORD` secrets are stale or the
SVN repo state diverged. Quickest recovery:

1. Manually run `npm run plugin-zip` locally on the tag's commit.
2. Upload the zip to <https://wordpress.org/plugins/developers/add/> as the new version, or use `svn` to push it through the wp.org SVN flow manually.
3. File an issue to rotate the secrets if that's the root cause.

The GitHub Release stays correct regardless — it's already attached the zip
and the release body.

### Auto-PR for the changelog rollup didn't open

The `rollup-changelog` job failed late (after the rollup commit pushed but
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
# fix up develop, then re-tag
git tag 0.34.0
git push origin 0.34.0
```

If the release workflow already ran against the bad tag, also:

- Delete the GitHub Release entry (it'll auto-recreate on the new tag push).
- Close the bogus auto-PR.
- Revert the wp.org deploy if it shipped — contact wp.org plugin team if you can't.

## Secrets and permissions required

The workflow uses these secrets from the repo / org settings:

- `SVN_USERNAME` — wp.org SVN username (used by the wp.org deploy step only).
- `SVN_PASSWORD` — wp.org SVN password (used by the wp.org deploy step only).
- `GITHUB_TOKEN` — auto-provisioned; the workflow declares least-privilege scopes per job (`contents: write` for the rollup commit + Release creation, `pull-requests: write` for the auto-PR).

If the `permissions:` block on `release.yml` is ever loosened, double-check
that no job ends up with broader scopes than it needs.

## Versioning conventions

- **Stable**: `0.34.0`, `0.35.0`, `1.0.0`. Three numeric components, no suffix. These ship to wp.org.
- **Alpha**: `0.34.0-alpha.1`, `0.34.0-alpha.2`. Use for early in-cycle builds.
- **Beta**: `0.34.0-beta.1`. Use for feature-complete in-cycle builds where the team is still smoke-testing.
- **Release candidate**: `0.34.0-rc.1`. Use for "we believe this is shippable, last call for showstoppers."

The pre-release suffix matches the SemVer spec — anything outside `-alpha.` / `-beta.` / `-rc.` won't be recognized by the workflow's classifier and will be treated as stable. Don't get creative with the suffix.
