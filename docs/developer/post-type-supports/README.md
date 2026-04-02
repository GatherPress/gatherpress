# Post Type Supports

GatherPress uses WordPress [post type supports](https://developer.wordpress.org/reference/functions/add_post_type_support/) to allow developers to enable GatherPress features on their own custom post types. This makes it possible to use event dates, venues, RSVPs, and other GatherPress functionality without being limited to the built-in `gatherpress_event` post type.

## Available Supports

### `gatherpress-event-date`

Enables event datetime storage and display for a post type. This includes:

- Registration of datetime meta fields (`gatherpress_datetime`, `gatherpress_datetime_start`, `gatherpress_datetime_end`, `gatherpress_timezone`, etc.)
- Storage in the `gatherpress_events` database table
- Date-based query ordering (upcoming/past)
- Event Date block rendering
- Add to Calendar block rendering
- RSS feed enrichment with event date information
- Post date override with event date (when enabled in settings)

#### Usage

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

#### Usage

```php
add_action( 'init', function() {
    add_post_type_support( 'my_custom_event', 'gatherpress-rsvp' );
}, 11 );
```

Or include it in your `register_post_type()` call:

```php
register_post_type( 'my_custom_event', array(
    'supports' => array( 'title', 'editor', 'gatherpress-event-date', 'gatherpress-rsvp' ),
    // ... other args
) );
```

### Planned Supports

The following supports are planned but not yet implemented:

- **`gatherpress-venue`** - Physical venue association via the `_gatherpress_venue` taxonomy
- **`gatherpress-online-event`** - Online event link meta field and online-event term

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

Similarly, queries that previously targeted only `gatherpress_event` now include all post types with the relevant support:

```php
// Before.
$args = array( 'post_type' => 'gatherpress_event' );

// After.
$args = array( 'post_type' => get_post_types_by_support( 'gatherpress-event-date' ) );
```

## Naming Convention

All GatherPress supports use the following naming convention:

- **Kebab-case** to match WordPress core conventions (e.g., `custom-fields`)
- **`gatherpress-` prefix** to avoid conflicts with other plugins

## Important Notes

- The `gatherpress_events` database table stores data by `post_id` and is post-type agnostic. Any post type with `gatherpress-event-date` support can store datetime data in this table.
- Supports must be registered before or during `init` (priority 10). GatherPress registers post-type-specific hooks inside `register_post_meta()` which loops over all post types with the relevant support.
- The `Event::POST_TYPE` constant still exists and refers to `gatherpress_event`. It is used for GatherPress's own post type registration but should not be used for feature checks.
