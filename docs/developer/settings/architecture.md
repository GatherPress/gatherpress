# Settings Architecture

This document covers the internal design of the GatherPress Settings API.

## Storage

All settings are stored in a single WordPress option:

```text
Option name: gatherpress_settings
Option value: array( 'key' => 'value', ... )
```

The constant `Settings::OPTION_NAME` (`'gatherpress_settings'`) is used throughout the codebase.

### Default Stripping

When settings are saved, values that match their configured defaults are removed from the option. This means:

- A fresh install has no `gatherpress_settings` option at all
- Only user-customized values are stored
- `Settings::get()` falls back to defaults for missing keys

This is handled in `sanitize_page_settings()`:

```php
foreach ( $merged as $key => $value ) {
    if ( $value === $this->get_flat_default( $key ) ) {
        unset( $merged[ $key ] );
    }
}
```

**Important:** Default values in field configs must use the correct PHP type to match what sanitization produces. For example, checkbox defaults should be `true`/`false` (not `'1'`/`'0'`), and number defaults should be integers (not strings).

## Defaults System

### get_flat_default()

Returns the default value for a single option key by looking it up in the cached defaults map.

### get_defaults_map()

Builds a flat map of all option keys to their default values by walking the sub-pages structure. The result is cached in `$defaults_cache` for the duration of the request to avoid repeated `apply_filters` + `uasort` calls.

```text
get( 'map_platform' )
  → not in gatherpress_settings option
  → get_flat_default( 'map_platform' )
    → get_defaults_map() (cached)
      → get_sub_pages() → apply_filters( 'gatherpress_sub_pages' )
      → walk all sections/options, collect defaults
    → return 'osm'
```

## Sanitization Pipeline

### Field Type Map

`build_field_type_map()` walks all registered sub-pages and produces a flat map of option key to field type:

```php
array(
    'post_or_event_date'    => 'checkbox',
    'map_platform'          => 'select',
    'max_attendance_limit'  => 'number',
    'date_format'           => 'text',
    'organizer'             => 'autocomplete',
    // ...
)
```

This map is also used for duplicate key detection. If any key appears more than once, an admin error notice is shown.

### sanitize_page_settings()

Returns a closure that:

1. Iterates submitted input and sanitizes each value by its field type:
   - `checkbox` -> `(bool)`
   - `number` -> `intval()`
   - `autocomplete` -> `sanitize_autocomplete()` (validates JSON structure)
   - `text`, `select` -> `sanitize_text_field()`
2. Merges sanitized input with existing option values (partial-save merge)
3. Strips values matching defaults

The partial-save merge is critical because each settings tab only submits its own fields. Without merging, saving one tab would wipe all other tabs' values.

## Sub-Pages Lifecycle

1. Plugin bootstrap: `Setup::instantiate_classes()` creates settings sub-page singletons
2. Each sub-page's `__construct()` calls `setup_hooks()` which registers the `gatherpress_sub_pages` filter
3. On `admin_init`, `Settings::register_settings()` fires:
   - Calls `get_sub_pages()` which fires the `gatherpress_sub_pages` filter
   - Each sub-page's `set_sub_page()` callback adds its page data (name, priority, sections)
   - Pages are sorted by priority
   - `register_setting()` is called once for `gatherpress_settings`
   - Sections and fields are registered via `add_settings_section()` / `add_settings_field()`

## render_field()

A single method handles all field type rendering. It:

1. Gets the field name attribute via `get_name_field()`: `gatherpress_settings[option_key]`
2. Gets the current value via `get()`
3. Builds common params (name, option, value, label, description)
4. Adds type-specific params via a switch statement
5. Renders the template at `templates/admin/settings/fields/{type}.php`

## Config-Driven Behaviors

### Preview

Fields can declare a `preview` key to render a live preview component:

```php
'preview' => array(
    'template' => 'datetime-preview',  // Template in partials/.
    'suffix'   => 'sample-event',      // Extra data passed to template.
)
```

The text field template checks for this config and renders the preview template if present.

### Rewrite

Fields can declare `'rewrite' => true` to indicate they affect permalink structure. `maybe_flush_rewrite_rules()` reads this config via `get_rewrite_keys()` and flushes rewrite rules when any flagged field changes value.

## Import/Export

### export_settings()

Returns the current option value with version metadata:

```php
array(
    'version'     => GATHERPRESS_VERSION,
    'exported_at' => current_time( 'c' ),
    'settings'    => get_option( 'gatherpress_settings', array() ),
)
```

### validate_import()

Dry-run validation that reports:

- Whether the data structure is valid
- Which keys would change
- Which keys are unknown (not registered)
- Version mismatch warnings

### import_settings()

Applies imported settings with two modes:

- **merge**: `array_merge( existing, imported )` -- preserves values not in the import file
- **replace**: clears existing option, applies only imported values

Both modes filter out unknown keys and run the full sanitization pipeline.

## Block Editor Integration

Settings are exposed to the block editor via the `block_editor_settings_all` filter in `Settings::add_editor_settings()`. The data is organized into two namespaces under `gatherpress`:

```text
select('core/editor').getEditorSettings().gatherpress
├── settings   — User-configurable values from the Settings API
│   ├── dateFormat
│   ├── timeFormat
│   ├── showTimezone
│   ├── mapPlatform
│   ├── maxAttendanceLimit
│   ├── maxGuestLimit
│   ├── enableAnonymousRsvp
│   ├── postOrEventDate
│   └── ... (any new settings are added automatically)
└── config     — Infrastructure values (not user-configurable)
    ├── pluginUrl
    ├── homeUrl
    ├── siteTimezone
    ├── timezoneChoices
    └── venuePostTypes (added by Venue::add_editor_settings)
```

### Accessing in JavaScript

Two helpers in `src/helpers/editor-settings.js` provide safe access:

```javascript
import { getFromSettings } from '../helpers/editor-settings';
import { getFromConfig } from '../helpers/editor-settings';

// User-configurable settings (from Settings API).
const dateFormat = getFromSettings( 'dateFormat' );
const mapPlatform = getFromSettings( 'mapPlatform' );

// Infrastructure config values.
const pluginUrl = getFromConfig( 'pluginUrl' );
const siteTimezone = getFromConfig( 'siteTimezone' );
```

**Important:** These helpers import `@wordpress/data` and must NOT be used in view scripts (`viewScriptModule` entries). WordPress script modules cannot import `@wordpress/data`. For frontend data, use `wp_interactivity_state()` or block data attributes instead.

### How Settings Are Added Automatically

`add_editor_settings()` iterates all keys from `get_defaults_map()` and converts them to camelCase using `Utility::snake_to_camel()`. This means any new setting registered via the `gatherpress_sub_pages` filter is automatically available in the editor without additional code.

### Frontend Data

For frontend view scripts, data is provided through:

- **`wp_interactivity_state('gatherpress', ...)`** in `Assets::add_interactivity_state()` — provides the REST API URL to the interactivity store
- **Block data attributes** in `render.php` — provides per-block values like `mapPlatform` and `pluginUrl` for the venue map

## Key Files

| File | Purpose |
|------|---------|
| `includes/core/classes/class-settings.php` | Main Settings class (Singleton) |
| `includes/core/classes/settings/class-base.php` | Abstract base for sub-page classes |
| `includes/core/classes/settings/class-events.php` | Events tab |
| `includes/core/classes/settings/class-rsvp-settings.php` | RSVP tab |
| `includes/core/classes/settings/class-formatting.php` | Formatting tab |
| `includes/core/classes/settings/class-roles.php` | Roles tab |
| `includes/core/classes/settings/class-tools.php` | Tools tab (import/export) |
| `includes/core/classes/settings/class-credits.php` | Credits tab |
| `includes/core/classes/commands/class-settings-cli.php` | WP-CLI commands |
| `includes/templates/admin/settings/` | Templates for settings UI |
| `src/helpers/editor-settings.js` | `getFromSettings()` and `getFromConfig()` JS helpers |
