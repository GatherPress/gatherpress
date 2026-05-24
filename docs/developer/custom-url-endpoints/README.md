# Custom URL Endpoints

GatherPress ships a custom URL endpoint API used by the calendar subsystem
to expose iCal downloads, off-site calendar redirects, and subscribable iCal
feeds. The same API is available for companion plugins to register their own
endpoints.

**Endpoints shipped by core:**

- `example.org/event/my-sample-event/ical`

   provides a downloadable .ics file in ical format.

- `example.org/event/my-sample-event/outlook`

   provides the same downloadable file as an alias.

- `example.org/event/my-sample-event/google-calendar`

   redirects to create a new event in *Google Calendar*.

- `example.org/event/my-sample-event/yahoo-calendar`

   redirects to create a new event in *Yahoo Calendar*.

- `example.org/feed/ical`

   provides a subscribable site-wide event feed in ical format.

- `example.org/event/feed/ical`

   provides a subscribable event feed in ical format for the events archive.

- `example.org/venue/my-sample-venue/feed/ical`

   provides a subscribable event feed in ical format with all events at that venue.

- `example.org/topic/my-sample-topic/feed/ical`

   provides a subscribable event feed in ical format with all events grouped into that topic.

The most obvious WordPress core functions for this are [`add_feed()`](https://developer.wordpress.org/reference/functions/add_feed/) and [`add_rewrite_endpoint()`](https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/). Both share a common pitfall — they're not restrictive to any post type at all, so a naive `/feed/ical` registration would attach the endpoint to every non-hierarchical custom post type on the site. GatherPress's endpoint helper sidesteps that by scoping registration to specific post types and taxonomies up front.

## GatherPress' own Endpoint API

The endpoint classes live under `GatherPress\Core\Calendar` ([`includes/core/classes/calendar/`](../../../includes/core/classes/calendar/)). Companion plugins can use them to declare endpoints against their own post types or taxonomies — the API isn't calendar-specific despite living in that namespace.

In general, one endpoint can be created …

- for individual posts
- for post type archives
- for taxonomy archives
- site-wide

It can be either …

- a redirect

  *or*

- a template to load

### Setup new endpoints

To create a new endpoint, instantiate one of the [`Endpoint`](../../../includes/core/classes/calendar/class-endpoint.php) subclasses:

- [`Post_Type_Single`](../../../includes/core/classes/calendar/class-post-type-single.php)

   for endpoints like `example.org/cpt/my-custom-post-type/new-endpoint`

- [`Post_Type_Single_Feed`](../../../includes/core/classes/calendar/class-post-type-single-feed.php)

   for endpoints like `example.org/cpt/my-custom-post-type/feed/new-endpoint`

- [`Post_Type_Feed`](../../../includes/core/classes/calendar/class-post-type-feed.php)

   for endpoints like `example.org/cpt/feed/new-endpoint`

- [`Taxonomy_Feed`](../../../includes/core/classes/calendar/class-taxonomy-feed.php)

   for endpoints like `example.org/ctax/feed/new-endpoint`

- [`Sitewide_Feed`](../../../includes/core/classes/calendar/class-sitewide-feed.php)

   for endpoints like `example.org/feed/new-endpoint`

These pick *where* an endpoint runs. To become callable, each endpoint also needs at least one of:

- [`Redirect`](../../../includes/core/classes/calendar/class-redirect.php) — for off-site redirects

  *or*

- [`Template`](../../../includes/core/classes/calendar/class-template.php) — for theme-overridable template output

## Example | Add events to *Office365 Calendar*

Example for a new redirection endpoint like `example.org/event/my-sample-event/office365-calendar`.

### 1. Setup a new endpoint

Set up a single-event endpoint via [`Post_Type_Single`](../../../includes/core/classes/calendar/class-post-type-single.php). Run it on `init` at a very high priority so every relevant post type and shadow taxonomy has finished registering — GatherPress core uses `PHP_INT_MAX` for this (see `Calendar\Setup::setup_hooks()`); companion plugins can pick any similarly high priority, with `99` being a safe default that still leaves room for downstream observers to hook after.

```php
use GatherPress\Core\Calendar\Post_Type_Single;
use GatherPress\Core\Calendar\Redirect;

add_action(
    'init',
    function () {
        new Post_Type_Single(
            array(
                new Redirect(
                    'office365-calendar',
                    array( $this, 'get_office365_calendar_link' )
                ),
            ),
            'gatherpress_awesome_calendar',
        );
    },
    99
);
```

> [!TIP]
> Earlier hook timings (`registered_post_type`) can fire before companion subsystems like the venue shadow-taxonomy wiring complete, leaving validation checks on rewrite registration falsely failing. Hooking late on `init` (priority `99` for companion plugins, `PHP_INT_MAX` for core) sidesteps that.

### 2. Define the callback for the endpoint

```php
use GatherPress\Core\Event\Event;

/**
 * Returns the Office 365 Calendar URL for the queried event.
 *
 * @since 1.0.0
 *
 * @return string The URL to redirect the user to the appropriate calendar service.
 */
public function get_office365_calendar_link(): string {
    $event       = new Event( get_queried_object_id() );
    $date_start  = $event->get_formatted_datetime( 'Ymd', 'start', false );
    $time_start  = $event->get_formatted_datetime( 'His', 'start', false );
    $date_end    = $event->get_formatted_datetime( 'Ymd', 'end', false );
    $time_end    = $event->get_formatted_datetime( 'His', 'end', false );

    // Format the start and end datetime in the required format.
    $startdt = sprintf( '%sT%sZ', $date_start, $time_start );
    $enddt   = sprintf( '%sT%sZ', $date_end, $time_end );

    $venue       = $event->get_venue_information();
    $location    = $venue['name'];
    $description = $event->get_calendar_description();

    // The venue info shape uses `address`, not `full_address` — earlier
    // drafts of this doc referenced the latter.
    if ( ! empty( $venue['address'] ) ) {
        $location .= sprintf( ', %s', $venue['address'] );
    }

    $params = array(
        'subject'  => sanitize_text_field( $event->event->post_title ),
        'body'     => sanitize_text_field( $description ),
        'startdt'  => $startdt,
        'enddt'    => $enddt,
        'location' => sanitize_text_field( $location ),
        'path'     => '/calendar/action/compose',
        'rru'      => 'addevent',
    );

    return add_query_arg(
        rawurlencode_deep( $params ),
        'https://outlook.office.com/calendar/0/deeplink/compose'
    );
}
```

### 3. Retrieve the endpoint URL

For the calendar endpoints shipped by core, instantiate the [`Calendar`](../../../includes/core/classes/calendar/class-calendar.php) class with the event ID and call the matching getter:

```php
use GatherPress\Core\Calendar\Calendar;

$calendar = new Calendar( $event_id );
$ical_url    = $calendar->get_ical_url();
$outlook_url = $calendar->get_outlook_url();
$google_url  = $calendar->get_google_url();
$yahoo_url   = $calendar->get_yahoo_url();
```

For companion-plugin endpoints (the Office 365 example above), build the URL the same way GatherPress does internally — wrap a small helper class around the post ID and concatenate the slug onto the post permalink, falling back to a query-arg form when permalinks are off or a path conflict exists. The shape of `Calendar::get_endpoint_url()` is the reference implementation.

## Filtering calendar URLs

Calendar URLs flow through the `gatherpress_calendar_url` filter before they leave the builder, so integrators can rewrite them without subclassing. The filter receives the full URL and the originating `WP_Post`:

```php
add_filter(
    'gatherpress_calendar_url',
    function ( string $endpoint_url, WP_Post $post ): string {
        // Route every calendar URL through a tracking-friendly subdomain.
        return str_replace( 'example.org', 'cal.example.org', $endpoint_url );
    },
    10,
    2
);
```

Typical use cases: routing calendar downloads through a CDN, swapping the host for a federation-friendly canonical, appending tracking params. The result is `sanitize_url()`-cleaned on return, so filters don't need to escape themselves.

## Resources

- Full, working code from the Office 365 example is part of **GatherPress Awesome**.

    Within [your GatherPress Awesome plugin](https://github.com/GatherPress/gatherpress-awesome), enable it in `gatherpress-awesome/includes/classes/class-setup.php`:

    ```php
    // ENABLE or DISABLE
    // Test adding some awesome endpoints!
    // Awesome_Endpoints::get_instance(); // <-- Un-Comment to ENABLE
    ```

    ```php
    // ENABLED
    // Test adding some awesome endpoints!
    Awesome_Endpoints::get_instance(); // <-- :tada:
    ```

### Testing & Validating

- [iCalendar Validator](https://icalendar.org/validator.html)
- [Monkeyman Rewrite Analyzer – WordPress plugin | WordPress.org](https://wordpress.org/plugins/monkeyman-rewrite-analyzer/)

#### *explicitly not used*, but related from WordPress

- [`add_feed()`](https://developer.wordpress.org/reference/functions/add_feed/)
- [`add_rewrite_endpoint()`](https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/)
