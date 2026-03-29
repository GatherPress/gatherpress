# Settings API

The GatherPress Settings API provides a structured way to manage plugin configuration. All settings are stored in a single flat WordPress option (`gatherpress_settings`) as key-value pairs. Only non-default values are persisted, keeping the database lean.

## Reading Settings

```php
use GatherPress\Core\Settings;

$settings = Settings::get_instance();

// Get a single setting (returns default if not set).
$map_platform = $settings->get( 'map_platform' ); // 'osm'
$max_limit    = $settings->get( 'max_attendance_limit' ); // 50
```

## Settings Page Tabs

The settings page is organized into tabs, each managed by its own class:

| Tab | Class | Description |
|-----|-------|-------------|
| Events | `Settings\Events` | Event display, archive pages, permalinks |
| RSVP | `Settings\Rsvp_Settings` | Attendance limits, guest limits, anonymous RSVP, cleanup |
| Formatting | `Settings\Formatting` | Date/time formats, timezone display, map platform |
| Roles | `Settings\Roles` | Organizer role assignment |
| Tools | `Settings\Tools` | Import and export settings |
| Credits | `Settings\Credits` | Plugin contributors |

## Available Settings

### Events Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `post_or_event_date` | checkbox | `true` | Display event date instead of publish date |
| `upcoming_events` | autocomplete | `[]` | Page for upcoming events archive |
| `past_events` | autocomplete | `[]` | Page for past events archive |
| `events_url` | text | `'event'` | Permalink base for events |
| `venues_url` | text | `'venue'` | Permalink base for venues |
| `topics_url` | text | `'topic'` | Permalink base for topics |

### RSVP Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `max_attendance_limit` | number | `50` | Maximum attendees per event (0 = unlimited) |
| `max_guest_limit` | number | `0` | Maximum guests per attendee (0-5) |
| `enable_anonymous_rsvp` | checkbox | `false` | Allow anonymous RSVPs |
| `rsvp_cleanup_switch` | select | `'off'` | Enable/disable RSVP cleanup |
| `rsvp_cleanup_frequency` | select | `'daily'` | Cleanup frequency (hourly, daily, weekly, monthly, yearly) |
| `rsvp_cleanup_interval` | number | `1` | Interval multiplier for cleanup frequency |

### Formatting Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `date_format` | text | WordPress date format | Date format for events |
| `time_format` | text | WordPress time format | Time format for events |
| `show_timezone` | checkbox | `true` | Display timezone for events |
| `map_platform` | select | `'osm'` | Map provider (osm or google) |

### Roles Tab

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `organizer` | autocomplete | `[]` | Users assigned as organizers |

## Import and Export

Settings can be exported and imported via the Tools tab or WP-CLI:

```bash
# Export settings to a file.
wp gatherpress settings export --file=settings.json

# Preview what an import would change.
wp gatherpress settings import settings.json --dry-run

# Import with merge (preserves existing settings not in the file).
wp gatherpress settings import settings.json

# Import with replace (overwrites all settings).
wp gatherpress settings import settings.json --mode=replace
```

## Further Reading

- [Extending Settings](extending-settings.md) -- How to add custom tabs, sections, and fields
- [Architecture](architecture.md) -- Storage, sanitization, defaults, and caching internals
