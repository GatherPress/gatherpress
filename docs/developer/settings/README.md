# Settings API

The GatherPress Settings API provides a structured way to manage plugin configuration. All settings are stored in a single flat WordPress option (`gatherpress_settings`) as key-value pairs. Only non-default values are persisted, keeping the database lean.

## Reading and Writing Settings

```php
use GatherPress\Core\Settings;

$settings = Settings::get_instance();

// Get a single setting (returns default if not set).
$map_platform = $settings->get( 'map_platform' ); // 'osm'
$capacity     = $settings->get( 'capacity' ); // 50

// Set a single setting. Values matching the default are automatically removed.
$settings->set( 'map_platform', 'google' );
```

## Settings Page Tabs

The settings page is organized into tabs, each managed by its own class:

| Tab | Class | Description |
|-----|-------|-------------|
| Events | `Settings\Events` | Date/time formats, event display, archive pages, permalinks |
| RSVP | `Settings\Rsvp` | Attendance limits, guest limits, anonymous RSVP, cleanup |
| Venues | `Settings\Venues` | Map platform (and future venue-specific config) |
| Roles | `Settings\Roles` | Organizer role assignment |
| Tools | `Settings\Tools` | Import and export settings |
| Credits | `Settings\Credits` | Plugin contributors |

## Available Settings

### Events Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `date_format` | text | WordPress date format | Date format for events |
| `time_format` | text | WordPress time format | Time format for events |
| `show_timezone` | checkbox | `true` | Display timezone for events |
| `use_event_date_for_publish` | checkbox | `true` | Display event date instead of publish date |
| `event_archive` | select | `'upcoming'` | What the events archive URL shows when no custom page is assigned (`upcoming`, `past` or `none`) |
| `upcoming_events` | autocomplete | `[]` | Page for upcoming events archive |
| `past_events` | autocomplete | `[]` | Page for past events archive |
| `events_url` | text | `'event'` | Permalink base for events |
| `topics_url` | text | `'topic'` | Permalink base for topics |

### RSVP Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `rsvp_mode` | select | `'enabled'` | Controls sitewide RSVP availability. See [RSVP Mode](#rsvp-mode) below. |
| `capacity` | number | `50` | Maximum attendees per event (0 = unlimited) |
| `guest_limit` | number | `0` | Maximum guests per attendee (0-5) |
| `enable_anonymous_rsvp` | checkbox | `false` | Allow anonymous RSVPs |
| `enable_open_rsvp` | checkbox | `true` | Allow visitors to RSVP without a site account (email verification). Enabled by default. |
| `enable_waiting_list_emails` | checkbox | `true` | Email attendees when they move off the waiting list |
| `enable_event_reminders` | checkbox | `true` | Email attendees a reminder before an event starts |
| `event_reminder_day_before` | checkbox | `true` | Send a reminder a day before the start |
| `event_reminder_hour_before` | checkbox | `false` | Send a reminder an hour before the start |
| `enable_rsvp_cleanup` | select | `'disabled'` | Enable/disable RSVP cleanup |
| `rsvp_cleanup_frequency` | select | `'daily'` | Cleanup frequency (hourly, daily, weekly, monthly, yearly) |
| `rsvp_cleanup_multiplier` | number | `1` | Interval multiplier for cleanup frequency |

The `gatherpress_event_reminder_timings` filter can add or remove reminder timings. Each one gets an `event_reminder_{timing}` checkbox.

#### RSVP Mode

The `rsvp_mode` setting controls how RSVP availability is determined across the site.

| Value | Label | Behavior |
|-------|-------|----------|
| `enabled` | All Events | RSVP is enabled for every event. No per-event toggle is shown. This is the default. |
| `per_event_enabled` | Per Event (default enabled) | A per-event toggle appears in the block editor. New events default to RSVP enabled. |
| `per_event_disabled` | Per Event (default disabled) | A per-event toggle appears in the block editor. New events default to RSVP disabled. |
| `disabled` | Disabled | RSVP is turned off sitewide. RSVP blocks are hidden from the inserter, the RSVPs admin submenu is removed, and the RSVP Settings editor panel is hidden. |

**Per-event meta convention:** the `gatherpress_enable_rsvp` post meta stores the per-event state as an integer (`1` = enabled, `0` = disabled). An unset meta value (empty string) is treated as enabled, only an explicit `0` disables RSVP for an individual event. When mode is `enabled`, this meta is explicitly written as `1` on save so that switching to a per-event mode later produces predictable results.

```php
use GatherPress\Core\Settings;

// Read the current RSVP mode.
$mode = Settings::get_instance()->get( 'rsvp_mode' ); // 'enabled'

// Check whether RSVP is enabled for a specific event post.
$enabled = ( new \GatherPress\Core\Rsvp( $post_id ) )->is_enabled();
```

### Venues Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `map_platform` | select | `'osm'` | Map provider (osm or google) |
| `venues_url` | text | `'venue'` | Permalink base for venues |

### Roles Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `organizer` | autocomplete | `[]` | Users assigned as organizers |

## Import and Export

Settings can be exported and imported via the Tools tab or WP-CLI:

```bash
# Export settings to a file.
wp gatherpress settings export --file=settings.json

# Preview what an import would change (default behavior).
wp gatherpress settings import settings.json

# Apply import with merge (preserves existing settings not in the file).
wp gatherpress settings import settings.json --apply

# Apply import with replace (overwrites all settings).
wp gatherpress settings import settings.json --apply --mode=replace
```

## Further Reading

- [Extending Settings](extending-settings.md) -- How to add custom tabs, sections, and fields
- [Architecture](architecture.md) -- Storage, sanitization, defaults, and caching internals
- [Multisite Network Settings](multisite.md) -- Network-wide values and per-option inheritance on WordPress Multisite
