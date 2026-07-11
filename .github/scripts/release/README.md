# Release tooling

Standalone PHP tooling for cutting GatherPress versions, replacing the
retired [GatherPress/gatherpress-develop](https://github.com/GatherPress/gatherpress-develop)
WP-CLI plugin (#1827). No WordPress install required — the only network
dependency is the profiles.wordpress.org REST API used to resolve credit
usernames.

The full release runbook lives at
[`docs/contributor/release-process.md`](../../../docs/contributor/release-process.md).

## Layout

| Path | Purpose |
| --- | --- |
| `generate-version.php` | The version bump: regenerates credits, patches version strings in place. |
| `credits/<version>.json` | **Source of truth** for per-version credits, one file per version (full history). Hand-edited. |

## What is generated vs hand-edited

Only two things are owned by the tooling:

- `includes/data/credits.php` is **fully generated** from the target
  version's `credits/<version>.json` (usernames resolved via
  profiles.wordpress.org). Never hand-edit the generated file.
- A handful of **version strings are patched in place** per release: the
  `gatherpress.php` Version header, the `package.json` version, the
  `README.md` version badge, `readme.txt`'s `Stable tag:` and
  `Contributors:` lines, and the `SECURITY.md` supported-versions table
  (core and the sibling `../gatherpress-alpha` checkout when present).

Everything else in `README.md`, `readme.txt`, and `SECURITY.md` is a normal
hand-edited file — improve them like any other file, no regeneration step.

## Cutting a version

1. Add the new version's credits file via a normal PR — a stable version's
   `credits/X.Y.Z.json` is a copy of its latest pre-release file, with any
   roster corrections applied to the pre-release file first. Group order in
   the generated output is fixed (leads, team, contributors) regardless of
   the file's key order.
2. Either run the **Version Bump** workflow (Actions → Version Bump → enter
   the version) — it bumps everything, refreshes the lockfile, and opens the
   `version-X.Y.Z` PR against develop — or run locally:

    ```bash
    npm run version:bump -- --version=0.35.0
    npm i --package-lock-only
    ```

3. GatherPress Alpha syncs in lockstep: when run locally with
   `../gatherpress-alpha` checked out, the script updates its version header
   and SECURITY.md too; in the workflow (no sibling checkout) it is skipped —
   open the alpha version PR separately.
