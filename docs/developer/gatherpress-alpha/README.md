# GatherPress Alpha

[GatherPress Alpha](https://github.com/GatherPress/gatherpress-alpha) is a companion plugin that runs the data migrations a GatherPress upgrade needs. It exists so core can change its mind about data shapes before 1.0 without carrying every past decision forward forever.

It is not a beta channel, and it is not optional extras. It is the migration runner.

## Why it is a separate plugin

GatherPress is pre-1.0 and still reshaping its data: block names change, attributes move, a taxonomy replaces a meta key. Those changes need one-time migrations against existing content.

Keeping migrations in core would mean core accumulating every migration it has ever needed, and running that accumulated weight on every install forever, including the brand new ones with nothing to migrate. Putting them in a separate plugin means:

- A new site never installs it, because there is nothing to migrate.
- A site upgrading through several versions installs it, applies, and can remove it again.
- Core's own codebase stays about running events rather than about its own history.

The README calls this managing technical debt on the way to 1.0, which is exactly what it is.

## Version lockstep

**Alpha's version must match core's exactly.** It checks on load and refuses to run on a mismatch, because a migration written for 0.36.0 makes assumptions about 0.36.0's data shapes and running it against a different version is how data gets corrupted rather than migrated.

The check is exact string equality, which has one practical consequence worth internalizing: mixing a core release with an Alpha from a different branch makes Alpha inert rather than noisy. It does not half-work.

This is why every core release needs a matching Alpha release. Alpha mirrors core's branch model for the same reason: its `develop` tracks core's `develop` and is where pre-releases are tagged, its `main` is the released state, and the pairing is one rule, core develop with alpha develop, core main with alpha main. The release train handles this, and the [release process](../../contributor/release-process.md) covers the sync PR and the handoff job.

## How core knows about it

Core does not depend on Alpha, and works fine without it. It only checks whether Alpha is present so it can prompt when it might be needed:

```php
$is_alpha_active = apply_filters(
	'gatherpress_is_alpha_active',
	defined( 'GATHERPRESS_ALPHA_VERSION' )
);
```

`Setup::check_gatherpress_alpha()` renders an admin notice when Alpha is absent, and it is deliberately narrow about when: only for users who can `install_plugins`, and only on the plugins, plugin-install and GatherPress screens. It does not follow people around their admin.

The `gatherpress_is_alpha_active` filter is the override, which is how tests exercise both branches without installing anything.

## Applying migrations

Two ways, both doing the same work.

**In the admin**: Events, Settings, Alpha, then **Apply Updates**. While anything is pending, Alpha shows a notice with a link straight to that screen.

**On the command line**:

```bash
wp gatherpress alpha fix
```

Which is the one to reach for on a multisite network or anywhere you are already scripting an upgrade. See [WP-CLI commands](../wp-cli/README.md) for GatherPress's own commands.

## Working on Alpha

The sibling checkout lives at `../gatherpress-alpha` relative to core, and core's release tooling looks for it there when it is present.

Two things to know before writing a migration:

- **Migrations run once, against real data, and are not undone.** Write them to be safe to re-run, because somebody will run them twice.
- **Alpha has no test suite by design.** Migrations are verified by running them against a real site with real content, which is the only place their assumptions actually get tested.

## For site owners

The user-facing version of all this, including how to pick the matching release and install it, is in [getting started](../../user/getting-started.md).

## See also

- [Release process](../../contributor/release-process.md), the version sync and release pairing
- [WP-CLI commands](../wp-cli/README.md)
- [Close to core](../philosophy/README.md), why this is a companion plugin rather than core code
