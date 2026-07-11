# Release tooling

Standalone PHP tooling for cutting GatherPress versions, ported from the
retired [GatherPress/gatherpress-develop](https://github.com/GatherPress/gatherpress-develop)
WP-CLI plugin (#1827). No WordPress install required — the only network
dependency is the profiles.wordpress.org REST API used to resolve credit
usernames.

The full release runbook lives at
[`docs/contributor/release-process.md`](../../../docs/contributor/release-process.md).

## Layout

| Path | Purpose |
| --- | --- |
| `generate-version.php` | The generator: credits, version strings, readmes, SECURITY.md. |
| `data/credits.php` | **Source of truth** for per-version credits (full history). Hand-edited. |
| `parts/` | Template parts assembled into `README.md` (github/ + shared/) and `readme.txt` (wporg/ + shared/). Hand-edited. |

`README.md`, `readme.txt`, `includes/data/credits.php`, and `SECURITY.md` at
the repo root are **generated output** — never edit them directly; edit the
parts or the credits data here and regenerate.

## Cutting a version

1. Add the new version's entry to `data/credits.php` (prepend; a stable entry
   is a copy of its latest pre-release entry) via a normal PR.
2. Either run the **Version Bump** workflow (Actions → Version Bump → enter
   the version) — it generates everything, refreshes the lockfile, and opens
   the `version-X.Y.Z` PR against develop — or run locally:

    ```bash
    php .github/scripts/release/generate-version.php --version=0.35.0
    npm i --package-lock-only
    ```

3. GatherPress Alpha syncs in lockstep: when run locally with
   `../gatherpress-alpha` checked out, the script updates its version header
   and SECURITY.md too; in the workflow (no sibling checkout) it is skipped —
   open the alpha version PR separately.

## Editing the feature list

`docs/features.md` is hand-maintained; the generated readmes' feature list
comes from `parts/shared/features.md`. Keep the two in sync when features
change.
