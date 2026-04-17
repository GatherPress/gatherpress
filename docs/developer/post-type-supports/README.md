# Post Type Supports

GatherPress uses WordPress [post type supports](https://developer.wordpress.org/reference/functions/add_post_type_support/) to allow developers to enable GatherPress features on their own custom post types. This makes it possible to use event dates, venues, RSVPs, and other GatherPress functionality without being limited to the built-in `gatherpress_event` or `gatherpress_venue` post types.

## Event Post Type Supports

These supports are declared on post types that act as **events**.

### `gatherpress-event-date`

The core identifier for event post types. Enables event datetime storage and display. This includes:

- Registration of datetime meta fields (`gatherpress_datetime`, `gatherpress_datetime_start`, `gatherpress_datetime_end`, `gatherpress_timezone`, etc.)
- Storage in the `gatherpress_events` database table
- Date-based query ordering (upcoming/past)
- Event Date block rendering
- Add to Calendar block rendering
- RSS feed enrichment with event date information
- Post date override with event date (when enabled in settings)

#### Usage for gatherpress-event-date

```php
add_action( 'init', function() {
    add_post_type_support( 'my_custom_event', 'gatherpress-event-date' );
}, 11 );
```

Or include it in your `register_post_type()` call:

```php
register_post_type( 'my_custom_event', array(
    'supports' => array( 'title', 'editor', 'gatherpress-event-date' ),
    // ... other args
) );
```

Once registered, you can use the `Event` class with your custom post type:

```php
use GatherPress\Core\Event;

$event = new Event( $my_custom_post_id );
$event->save_datetimes( array(
    'post_id'        => $my_custom_post_id,
    'datetime_start' => '2025-06-15 10:00:00',
    'datetime_end'   => '2025-06-15 12:00:00',
    'timezone'       => 'America/New_York',
) );
```

### `gatherpress-rsvp`

Enables the comment-based RSVP system for a post type. This includes:

- RSVP response tracking (attending, not attending, waiting list)
- Attendee management and waiting list processing
- RSVP blocks rendering (rsvp, rsvp-form, rsvp-response, rsvp-template)
- RSVP token-based email verification for anonymous attendees
- Comment count adjustment to reflect RSVP activity

#### Usage for gatherpress-rsvp

```php
add_action( 'init', function() {
    add_post_type_support( 'my_custom_event', 'gatherpress-rsvp' );
}, 11 );
```

### `gatherpress-venue`

Enables physical venue association for a post type. This includes:

- Registration of the `_gatherpress_venue` taxonomy for the post type
- Venue selector in the block editor
- Venue block rendering (name, address, map, phone, website)
- Venue detail field visibility (hides empty address/phone/website blocks)

#### Usage for gatherpress-venue

```php
add_action( 'init', function() {
    add_post_type_support( 'my_custom_event', 'gatherpress-venue' );
}, 11 );
```

> **Note:** Register `gatherpress-venue` support at priority 10 (the default for `register_post_type()`) so GatherPress's priority-11 taxonomy registration can discover your post type via `get_post_types_by_support()`. For standalone `add_post_type_support()` calls after registration, use priority 11 or later.

You can also override the venue post type used for lookups via the `gatherpress_venue_post_type` filter. The filter receives the event post type as a second argument, enabling per-event-type venue post type overrides:

```php
add_filter( 'gatherpress_venue_post_type', function( $post_type, $event_post_type ) {
    if ( 'my_custom_event' === $event_post_type ) {
        return 'my_custom_venue';
    }
    return $post_type;
}, 10, 2 );
```

### `gatherpress-online-event`

Enables online event functionality for a post type. This includes:

- Online event toggle and link field in the block editor inspector
- Online Event block rendering (icon and link)
- Association with the `online-event` term in the `_gatherpress_venue` taxonomy

#### Usage for gatherpress-online-event

```php
add_action( 'init', function() {
    add_post_type_support( 'my_custom_event', 'gatherpress-online-event' );
}, 11 );
```

---

## Venue Post Type Supports

These supports are declared on post types that act as **venues**. `gatherpress-venue-information` is the core identifier — declaring it is what makes a post type a venue source.

### `gatherpress-venue-information`

The core identifier for venue post types. Enables venue address and contact data. This includes:

- Registration of the `gatherpress_venue_information` meta field (JSON: address, phone, website, lat/lng)
- Registration of [WordPress Geodata standard](https://codex.wordpress.org/Geodata) meta keys (`geo_latitude`, `geo_longitude`, `geo_address`, `geo_public`) as read-only post meta. These are derived from `gatherpress_venue_information` on `wp_after_insert_post` and exposed so any plugin that follows the standard (e.g. [Simple Location](https://wordpress.org/plugins/simple-location/)) can interoperate with GatherPress venues without parsing our JSON. `geo_public` is bound to `post_status` (`1` when published, `0` otherwise). Meta revisions are enabled automatically when your venue post type declares `revisions` in its `supports` array.
- Venue detail blocks (address, phone number, website)
- Automatic creation and management of the corresponding `_gatherpress_venue` taxonomy term
- `post_type_supports( $type, 'gatherpress-venue-information' )` is the canonical check for "is this a venue?"

#### Usage for gatherpress-venue-information

```php
register_post_type( 'my_custom_venue', array(
    'supports' => array( 'title', 'editor', 'gatherpress-venue-information' ),
    // ... other args
) );
```

> **Note:** The `geo_*` meta keys are derived — do not write to them directly. Update `gatherpress_venue_information` and the geo meta will be rewritten on the next save. REST API writes to `geo_latitude`, `geo_longitude`, `geo_address`, or `geo_public` are silently stripped.

### `gatherpress-venue-map`

Enables map display for a venue post type. This includes:

- Registration of map meta fields (`gatherpress_venue_map_show`, `gatherpress_venue_map_zoom`, `gatherpress_venue_map_height`)
- Venue Map block rendering

#### Usage for gatherpress-venue-map

```php
register_post_type( 'my_custom_venue', array(
    'supports' => array( 'title', 'editor', 'gatherpress-venue-information', 'gatherpress-venue-map' ),
    // ... other args
) );
```

---

## How It Works

GatherPress replaces hardcoded post type checks with `post_type_supports()` calls. For example, instead of:

```php
// Before: only works with gatherpress_event.
if ( Event::POST_TYPE === get_post_type( $post_id ) ) {
    // Handle event date logic.
}
```

GatherPress now uses:

```php
// After: works with any post type that has gatherpress-event-date support.
if ( post_type_supports( get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
    // Handle event date logic.
}
```

The same pattern applies on the venue side:

```php
// Before: only works with gatherpress_venue.
if ( Venue::POST_TYPE === get_post_type( $post_id ) ) {
    // Handle venue logic.
}

// After: works with any post type that has gatherpress-venue-information support.
if ( post_type_supports( get_post_type( $post_id ), 'gatherpress-venue-information' ) ) {
    // Handle venue logic.
}
```

Similarly, queries that previously targeted only `gatherpress_event` or `gatherpress_venue` now include all post types with the relevant support:

```php
// Event queries.
$args = array( 'post_type' => get_post_types_by_support( 'gatherpress-event-date' ) );

// Venue queries.
$args = array( 'post_type' => get_post_types_by_support( 'gatherpress-venue-information' ) );
```

In JavaScript, support checks use the WordPress data store:

```js
// Check if current post type is an event.
select( 'core' ).getPostType( postType )?.supports?.[ 'gatherpress-event-date' ];

// Check if current post type is a venue.
select( 'core' ).getPostType( postType )?.supports?.[ 'gatherpress-venue-information' ];
```

---

## Naming Convention

All GatherPress supports use the following naming convention:

- **Kebab-case** to match WordPress core conventions (e.g., `custom-fields`)
- **`gatherpress-` prefix** to avoid conflicts with other plugins

## Important Notes

- The `gatherpress_events` database table stores data by `post_id` and is post-type agnostic. Any post type with `gatherpress-event-date` support can store datetime data in this table.
- Supports must be registered before or during `init`. Use **priority 10** (the default) for your `register_post_type()` call. GatherPress itself runs its meta registration and taxonomy setup at priority 11 — this ordering ensures your post type is discoverable via `get_post_types_by_support()` when those hooks fire.
- The `Event::POST_TYPE` constant still exists and refers to `gatherpress_event`. It is used for GatherPress's own post type registration but should not be used for feature checks.
- The `Venue::POST_TYPE` constant still exists and refers to `gatherpress_venue`. It is used for GatherPress's own post type registration but should not be used for feature checks.
- The `venuePostTypes` map is exposed to the block editor via `block_editor_settings_all` under `settings.gatherpress.venuePostTypes`. It maps event post type slugs to their corresponding venue post type slugs, resolved via the `gatherpress_venue_post_type` filter.
