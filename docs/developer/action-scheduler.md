# Action Scheduler

GatherPress uses [Action Scheduler](https://actionscheduler.org/) — a persistent, retry-capable job queue originally developed by WooCommerce — for background work that doesn't fit WP-Cron. The library is installed as a Composer dependency (`woocommerce/action-scheduler`), pinned via `composer.lock`, and landed at `includes/libraries/action-scheduler/` via the [`composer/installers`](https://github.com/composer/installers) plugin. It's **not** committed to the repo.

## Why we use it

WP-Cron has a few sharp edges for bulk work: jobs drain on the next pageview (so a low-traffic site doesn't process them), scheduling N identical hooks walks the `cron` option N times, and there's no built-in retry or throttling. Action Scheduler solves all three — it stores jobs in its own DB tables, runs them via a queue runner, retries with backoff on failure, and exposes the whole queue through a **Tools → Scheduled Actions** admin page.

Any time you're tempted to reach for `wp_schedule_single_event()` or `wp_schedule_event()` for anything that fans out to tens or hundreds of jobs, reach for Action Scheduler instead.

## Why it's not committed to the repo

Action Scheduler is ~800KB of upstream source. Committing it would be the equivalent of committing `vendor/` or `node_modules/` — noisy diffs every version bump, code review clutter, git history bloat for code we don't maintain. Composer already solves this problem: `composer.json` pins the version, `composer.lock` locks the exact resolved install, and `composer install` materializes the library wherever it's needed.

## How it's wired up

The relevant pieces in `composer.json`:

```json
{
  "require": {
    "composer/installers": "^2.0",
    "woocommerce/action-scheduler": "3.9.3"
  },
  "extra": {
    "installer-paths": {
      "includes/libraries/{$name}/": [
        "woocommerce/action-scheduler"
      ]
    }
  },
  "config": {
    "allow-plugins": {
      "composer/installers": true
    }
  }
}
```

`composer/installers` is the official Composer plugin that lets packages declare where they want to land. The `installer-paths` stanza above overrides the default `vendor/woocommerce/action-scheduler/` path so AS ends up at `includes/libraries/action-scheduler/` — the path the plugin's bootstrap loads from.

Where `composer install` runs:

| Context | How AS lands |
|---|---|
| Fresh clone for local dev | `composer install` (part of the standard two-step setup alongside `npm install`) |
| `npm run plugin-zip` | `libraries:install` npm script runs `composer install --no-dev --optimize-autoloader` before the zip is composed |
| CI: PHPUnit, PHPStan, PHPMD, PR coverage, SonarCloud, etc. | Each workflow already runs `composer install` |
| WordPress.org deploy workflow | Runs `composer install --no-dev --optimize-autoloader --prefer-dist` before the build step |

`.distignore` excludes `/vendor/`, so the test-only packages in `vendor/` never ship. Only AS (at `includes/libraries/action-scheduler/`) and other composer-vendored libraries under `includes/libraries/` make it into the zip.

## Using Action Scheduler in plugin code

```php
use GatherPress\Core\Action_Scheduler;

// Always gate on availability — a rare botched deploy or pruned library
// directory leaves the plugin running without AS, and the call site
// needs a fallback (typically WP-Cron or inline execution).
if ( Action_Scheduler::is_available() ) {
    as_enqueue_async_action(
        'gatherpress_warm_venue_map',           // Hook name.
        array( 'post_id' => $post_id ),         // Hook args.
        'gatherpress_prewarm'                   // Group — shows up as a filter in the Tools → Scheduled Actions UI.
    );
}
```

Other useful entry points:

- `as_schedule_single_action( $timestamp, $hook, $args, $group )` — run once at a specific time.
- `as_schedule_recurring_action( $timestamp, $interval, $hook, $args, $group )` — run on a recurring schedule.
- `as_unschedule_action( $hook, $args, $group )` — cancel the next pending match.
- `as_has_scheduled_action( $hook, $args, $group )` — built-in dedup check, replaces custom `wp_next_scheduled` bookkeeping.

Register a processor for your hook the same way you would for a WP-Cron action:

```php
add_action( 'gatherpress_warm_venue_map', array( $this, 'process_warm_job' ), 10, 1 );
```

Use a `gatherpress_`-prefixed group name per subsystem (`gatherpress_prewarm`, `gatherpress_rsvp_emails`, etc.) so the admin UI groups related jobs under one filter.

## Updating the pinned version

Same workflow as any other composer dependency bump:

```sh
composer require woocommerce/action-scheduler:3.9.4
```

Commit `composer.json` and `composer.lock` together. CI and the deploy workflow will converge on the new version automatically on the next run.

## What happens when another plugin also bundles Action Scheduler

Action Scheduler is designed to be bundled by many plugins at once (WooCommerce, WP Job Manager, GiveWP, and the standalone [Action Scheduler plugin](https://wordpress.org/plugins/action-scheduler/) all ship it). The library arbitrates between copies automatically:

1. Every bundled copy has a version-specific registration shim (e.g. `action_scheduler_register_3_dot_9_dot_3()`) guarded by `function_exists()` so the same version can't register twice.
2. On `plugins_loaded` priority 0, each copy calls `ActionScheduler_Versions::instance()->register( $version, $init_callback )` to add itself to a registry.
3. On `plugins_loaded` priority 1, `ActionScheduler_Versions::initialize_latest_version()` iterates the registry, picks the highest version via `version_compare`, and runs only its init callback.
4. Lower versions never run their init logic. The `as_*` functions the init callback defines are `function_exists()`-guarded, so only the winning copy provides them.

Net effect — we don't need any collision-detection code:

- **WooCommerce bundles 3.8.0, we bundle 3.9.3** → everyone uses 3.9.3 (ours wins).
- **WooCommerce bundles 3.10.0, we bundle 3.9.3** → everyone uses 3.10.0 (WooCommerce's wins).
- **Standalone AS plugin is active** → whichever has the higher version wins; the Tools → Scheduled Actions admin page is provided once regardless of which copy runs.

Bumping our pinned version doesn't cause pain for sites that already have a newer copy through another plugin — their existing copy just keeps winning.

## The admin UI

Once jobs have been enqueued, site admins can inspect, retry, or cancel them under **Tools → Scheduled Actions**. The page is provided entirely by Action Scheduler. Jobs filter by status (pending, in-progress, complete, failed, canceled) and by group.

## Fallback behavior

If Action Scheduler is somehow unavailable (library directory missing, host-level constraint), `Action_Scheduler::is_available()` returns false and the rest of the plugin continues to work — individual subsystems decide whether to fall back to WP-Cron, run the work inline, or skip it entirely. Don't assume AS is always loaded; always gate the enqueue call.
