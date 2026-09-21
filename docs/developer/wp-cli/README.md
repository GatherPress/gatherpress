# WP-CLI commands

GatherPress registers its commands under a single `gatherpress` namespace, in [`Core\Cli`](../../../includes/core/classes/class-cli.php). They only exist when WP-CLI is loaded, so nothing is added to a normal page request.

Two command groups ship with the plugin today:

| Command | What it does |
|---|---|
| [`wp gatherpress event rsvp`](#wp-gatherpress-event-rsvp) | Record or change a person's RSVP to an event |
| [`wp gatherpress settings export`](#wp-gatherpress-settings-export) | Write the plugin's settings out as JSON |
| [`wp gatherpress settings import`](#wp-gatherpress-settings-import) | Read settings back in, with a dry run by default |

Every command supports WP-CLI's own global flags, so `--url` picks a site on multisite and `--user` changes who the command runs as.

Run `wp help gatherpress event rsvp` for the same reference from your terminal — WP-CLI builds it from the command's docblock.

## `wp gatherpress event rsvp`

Records an RSVP, or changes one that already exists. This is the same path the front-end RSVP block takes, so the same rules apply: the waiting list fills when the event is at capacity, and promotions happen automatically when somebody drops out.

```bash
wp gatherpress event rsvp --event_id=<id> --user_id=<id> [--status=<status>] [--guests=<n>] [--anonymous=<0|1>]
```

| Option | Default | Notes |
|---|---|---|
| `--event_id` | — | The event to RSVP to |
| `--user_id` | — | The person RSVPing |
| `--status` | `attending` | One of `attending`, `not_attending`, `waiting_list` |
| `--guests` | `0` | How many people they are bringing |
| `--anonymous` | `0` | `1` hides them from the public response list |

```bash
# RSVP as attending.
wp gatherpress event rsvp --event_id=525 --user_id=1

# Cancel.
wp gatherpress event rsvp --event_id=525 --user_id=1 --status=not_attending

# Bring two guests, without appearing in the public list.
wp gatherpress event rsvp --event_id=525 --user_id=1 --guests=2 --anonymous=1
```

The command targets any post type that declares the `gatherpress-rsvp` support, not just `gatherpress_event` — see [post type supports](../post-type-supports/README.md). A post that does not support RSVPs is refused:

```text
Error: Event ID "525" does not exist or does not support RSVPs.
```

> [!IMPORTANT]
> **`--guests` and `--anonymous` are subject to the event's own settings**, exactly as they are on the front end. Guests are clamped to the event's `gatherpress_max_guest_limit`, which is `0` unless somebody raised it, and `--anonymous=1` is ignored unless the event has `gatherpress_enable_anonymous_rsvp` turned on. In both cases the command still reports success — it has recorded the RSVP, just not the part the event does not allow. If a guest count silently comes back as zero, check the event before suspecting the command.

## `wp gatherpress settings export`

Writes every GatherPress setting to JSON. With no `--file` it goes to stdout, so it pipes.

```bash
# Look at the current settings.
wp gatherpress settings export

# Save them.
wp gatherpress settings export --file=gatherpress-settings.json
```

The export deliberately leaves out settings marked non-exportable, which is how credentials and site-specific values stay out of a file that gets copied between installs. [Extending settings](../settings/extending-settings.md) covers the `exportable` flag, and [the settings architecture](../settings/architecture.md) covers the format.

On multisite the export carries a `scope` field recording whether it came from a single site or from network admin, so an import knows where it belongs. See [multisite settings](../settings/multisite.md).

## `wp gatherpress settings import`

Reads a file produced by `export`. **It does not change anything unless you pass `--apply`** — without that flag it prints what would change and stops.

```bash
# Preview. Changes nothing.
wp gatherpress settings import gatherpress-settings.json

# Apply, keeping settings the file does not mention.
wp gatherpress settings import gatherpress-settings.json --apply

# Apply, discarding settings the file does not mention.
wp gatherpress settings import gatherpress-settings.json --apply --mode=replace
```

| Option | Default | Notes |
|---|---|---|
| `<file>` | — | Path to the JSON file. Positional, not a flag |
| `--mode` | `merge` | `merge` keeps settings absent from the file; `replace` clears them |
| `--apply` | off | Without it, the command is a dry run |

Settings marked non-exportable are also not importable, so a hand-edited file cannot be used to push them in.

## Commands from companion plugins

[GatherPress Alpha](https://github.com/GatherPress/gatherpress-alpha) adds its own command for repairing data after an upgrade:

```bash
wp gatherpress alpha fix
```

It is documented with the plugin itself, not here — see [getting started](../../user/getting-started.md).

## Adding a command

Commands are registered in one place, in `Core\Cli`'s constructor:

```php
WP_CLI::add_command( 'gatherpress event', Event_Cli::class );
WP_CLI::add_command( 'gatherpress settings', Settings_Cli::class );
```

A command class lives in [`includes/core/classes/commands/`](../../../includes/core/classes/commands/), extends `WP_CLI`, and turns each public method into a subcommand. The docblock is not decoration: WP-CLI parses `## OPTIONS` into the command's synopsis and **rejects any argument that is not declared there**, so an option you read in the method body but forget in the docblock will never reach it.

```php
/**
 * One-line description, which becomes the command's summary.
 *
 * ## OPTIONS
 *
 * [--guests=<guests>]
 * : Number of guests accompanying the attendee.
 * ---
 * default: 0
 * ---
 *
 * ## EXAMPLES
 *
 *    # What somebody would actually type.
 *    $ wp gatherpress event rsvp --event_id=525 --user_id=1
 *
 * @since TBD
 *
 * @param string[]                   $args       Positional arguments.
 * @param array<string, string|bool> $assoc_args Associative arguments.
 *
 * @return void
 */
public function rsvp( array $args = array(), array $assoc_args = array() ): void {
```

Two things worth copying from the existing commands:

- **A bare `--flag` with no value arrives as `true`, not a string.** `Settings_Cli::export()` checks `is_string()` before treating `--file` as a path, because `wp gatherpress settings export --file` would otherwise try to write to `1`.
- **Destructive commands default to a dry run.** `settings import` requires `--apply` before it writes anything, which makes the safe invocation the short one.
