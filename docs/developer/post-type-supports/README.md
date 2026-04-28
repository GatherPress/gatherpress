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

Declare the support inside your `register_post_type()` call:

```php
register_post_type( 'my_custom_event', array(
    'supports' => array( 'title', 'editor', 'gatherpress-event-date' ),
    // ... other args
) );
```

> **Declare supports on registration, not after.** GatherPress wires its meta registration, admin-list columns, REST filters, and other per-post-type hooks on the `registered_post_type` action — i.e. at the moment your post type finishes registering. Calling `add_post_type_support( 'my_custom_event', 'gatherpress-event-date' )` *after* `register_post_type()` will make `post_type_supports()` return true, but GatherPress's internal wiring won't run for your post type. Always include the support in the `supports` array.

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
register_post_type( 'my_custom_event', array(
    'supports' => array( 'title', 'editor', 'gatherpress-event-date', 'gatherpress-rsvp' ),
    // ... other args
) );
```

### `gatherpress-venue`

Enables physical venue association for a post type. This includes:

- Registration of the `_gatherpress_venue` taxonomy for the post type
- Venue selector in the block editor
- Venue block rendering (name, address, map, phone, website)
- Venue detail field visibility (hides empty address/phone/website blocks)

#### Usage for gatherpress-venue

```php
register_post_type( 'my_custom_event', array(
    'supports' => array( 'title', 'editor', 'gatherpress-event-date', 'gatherpress-venue' ),
    // ... other args
) );
```

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
register_post_type( 'my_custom_event', array(
    'supports' => array( 'title', 'editor', 'gatherpress-event-date', 'gatherpress-online-event' ),
    // ... other args
) );
```

---

## Venue Post Type Supports

These supports are declared on post types that act as **venues**. `gatherpress-venue-information` is the core identifier — declaring it is what makes a post type a venue source.

### `gatherpress-venue-information`

The core identifier for venue post types. Enables venue address and contact data. This includes:

- Registration of five individual editor-writable post meta keys, each `show_in_rest` and bindable via `core/post-meta` block bindings:
    - `gatherpress_address`
    - `gatherpress_latitude`
    - `gatherpress_longitude`
    - `gatherpress_phone`
    - `gatherpress_website`
- Registration of eight server-populated structured-address meta keys, each `show_in_rest` for read access (REST writes are stripped, since these are derived from `gatherpress_address` by an async geocode cron handler that fires only when the address actually changes):
    - `gatherpress_house_number`
    - `gatherpress_street`
    - `gatherpress_city`
    - `gatherpress_county`
    - `gatherpress_state`
    - `gatherpress_postcode`
    - `gatherpress_country`
    - `gatherpress_country_code`
- Venue detail blocks (address, phone number, website)
- Automatic creation and management of the corresponding `_gatherpress_venue` taxonomy term
- `post_type_supports( $type, 'gatherpress-venue-information' )` is the canonical check for "is this a venue?"

Meta revisions are enabled automatically when your venue post type declares `revisions` in its `supports` array; venue post types that opt out of revisions still get the meta registered without `revisions_enabled`.

Meta registration itself lives on `GatherPress\Core\Venue\Meta::register()`. The companion field-list constants are `Venue\Meta::EDITOR_WRITABLE_FIELDS` (the five editor-writable suffixes) and `Venue\Meta::STRUCTURED_ADDRESS_FIELDS` (the eight Photon-derived suffixes) — those are the single source of truth for registration, REST stripping, the geocode cron write loop, and `Venue::get_information()`. The matching event-side class is `GatherPress\Core\Event\Meta`.

#### Structured-address fields

The eight structured-address fields are populated by a server-side cron handler that runs on a 5-second delay after `gatherpress_address` changes. Manual edits to those fields via `update_post_meta()` from trusted server code are preserved as long as the address itself doesn't change. To suppress the outbound HTTP-on-save (firewalled installs, dev environments without Photon access), return `false` from the `gatherpress_geocode_on_save_enabled` filter. To replace WP-Cron with a different scheduler (e.g. Action Scheduler), short-circuit the `gatherpress_async_geocode_pre_enqueue_job` filter with any non-null value.

The address autocomplete and save-time reverse-geocode that drive these fields go through two REST endpoints (`/gatherpress/v1/geocode` and `/gatherpress/v1/geocode/search`), both of which share a per-user fixed-window rate limit. The default ceiling is 30 requests per 60 seconds; the (N+1)th request returns HTTP `429 Too Many Requests` with a `Retry-After` header. Lower or raise the ceiling via the `gatherpress_geocode_rate_limit_per_minute` filter (values below `1` are clamped to `1`). To disable the rate limit entirely — for example when a CDN / WAF already covers this surface — return `false` from `gatherpress_geocode_rate_limit_enabled`.

#### Usage for gatherpress-venue-information

```php
register_post_type( 'my_custom_venue', array(
    'supports' => array( 'title', 'editor', 'gatherpress-venue-information' ),
    // ... other args
) );
```

#### Block bindings

Because each field is its own meta key, you can bind core blocks (paragraph, heading, button, etc.) to a venue field directly without an intermediate JSON parse step. For example, a paragraph bound to the venue's full address:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"gatherpress_address"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

### `gatherpress-venue-map`

Enables map display for a venue post type. This includes:

- Registration of map meta fields (`gatherpress_map_show`, `gatherpress_map_zoom`, `gatherpress_map_height`)
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

In JavaScript, support checks go through one of two helpers in `src/helpers/event.js`:

```js
import {
	isPostTypeSupporting,
	usePostTypeSupports,
} from '../../helpers/event';

// Outside React: imperative check that reads the post-type registry once.
isPostTypeSupporting( 'gatherpress-event-date', postType );

// Inside a React component: reactive check via useSelect, so the component
// re-renders the moment the post-type definition resolves.
const isEvent = usePostTypeSupports( 'gatherpress-event-date', postType );
```

**Always reach for `usePostTypeSupports` when the result drives rendering** — opacity, visibility, conditional inspector controls, etc. The non-reactive `isPostTypeSupporting` reads `select('core').getPostType(...)` directly, and the post-type registry usually isn't cached on first render. If a dim gate is wired through the non-reactive helper, the gate resolves to `false` on the first paint and the component never re-renders once supports load — leaving the block permanently dimmed in Query Loops.

For blocks that gate dimming on both context support and data presence, `hasValidBlockContext` in `src/helpers/editor.js` accepts a pre-computed `hasSupport` boolean — pass the result of `usePostTypeSupports`:

```js
const hasSupport = usePostTypeSupports( 'gatherpress-venue', context?.postType );

const blockProps = useBlockProps( {
	style: {
		opacity: hasValidBlockContext( {
			isDescendentOfQueryLoop,
			hasSupport,
			hasData: hasVenue,
		} ) ? 1 : DISABLED_FIELD_OPACITY,
	},
} );
```

Reading the post type from block context (`context?.postType`) requires `postType` to be declared in the block's `block.json` `usesContext` array — otherwise `context.postType` will be `undefined` inside a Query Loop's Post Template even when the queried post type would carry the relevant supports.

---

## Naming Convention

All GatherPress supports use the following naming convention:

- **Kebab-case** to match WordPress core conventions (e.g., `custom-fields`)
- **`gatherpress-` prefix** to avoid conflicts with other plugins

## Important Notes

- The `gatherpress_events` database table stores data by `post_id` and is post-type agnostic. Any post type with `gatherpress-event-date` support can store datetime data in this table.
- **Declare supports in your `register_post_type()` call, not via a later `add_post_type_support()`.** GatherPress registers its per-post-type hooks (meta fields, admin-list columns, REST query filters, venue save hooks) on the `registered_post_type` action, which only fires when `register_post_type()` runs. A support added afterwards will pass `post_type_supports()` checks but won't trigger any of GatherPress's internal wiring.
- The `Event::POST_TYPE` constant still exists and refers to `gatherpress_event`. It is used for GatherPress's own post type registration but should not be used for feature checks.
- The `Venue::POST_TYPE` constant still exists and refers to `gatherpress_venue`. It is used for GatherPress's own post type registration but should not be used for feature checks.
- The `venuePostTypes` map is exposed to the block editor via `block_editor_settings_all` under `settings.gatherpress.venuePostTypes`. It maps event post type slugs to their corresponding venue post type slugs, resolved via the `gatherpress_venue_post_type` filter.
