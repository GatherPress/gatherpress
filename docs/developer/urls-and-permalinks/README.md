# URLs and permalinks

Every public URL GatherPress generates, where its slug comes from, and what happens when you change one.

## The URL scheme

With pretty permalinks on, and using the default slugs:

| URL | What it is |
|---|---|
| `/event/` | The events archive |
| `/event/page/2/` | Archive pagination |
| `/event/{slug}/` | A single event |
| `/event/{slug}/ical` | Download that event as an `.ics` file |
| `/event/{slug}/outlook` | The same `.ics` file, named for Outlook |
| `/event/{slug}/google-calendar` | Redirect to Google Calendar with the event prefilled |
| `/event/{slug}/yahoo-calendar` | Redirect to Yahoo Calendar with the event prefilled |
| `/event/feed/` | Ordinary RSS for events |
| `/event/feed/ical/` | Subscribable calendar of every event |
| `/venue/` | The venues archive |
| `/venue/{slug}/` | A single venue |
| `/venue/{slug}/feed/ical/` | Subscribable calendar of that venue's events |
| `/topic/{slug}/` | A topic archive |
| `/topic/{slug}/feed/ical/` | Subscribable calendar of that topic's events |
| `/feed/ical/` | Subscribable calendar of the whole site |

Two distinctions are easy to miss:

- **A download is not a subscription.** `/event/{slug}/ical` hands over a one-time `.ics` file for a single event. The `/feed/ical/` routes are live calendars a person subscribes to once and their calendar app re-fetches. That is why the download hangs off a single event and the feeds hang off archives, venues and topics.
- **Venues have no single-event download**, because a venue is not an event. A venue gets a feed of its events instead.

The user-facing side of this is in [calendar feeds](../../user/calendar-feeds.md).

## Where the slugs come from

`event`, `venue` and `topic` are defaults, not constants. Three settings control them, all under Settings, GatherPress:

| Setting | Controls |
|---|---|
| `events_url` | The event post type's slug |
| `venues_url` | The venue post type's slug |
| `topics_url` | The topic taxonomy's slug |

Each renders with the `url-rewrite-preview` field template, so the admin sees the resulting URL as they type rather than having to imagine it.

### The defaults are localized

This is the part that surprises people reading the table above. The defaults are not the literal strings `event` and `venue`: they come from `Setup::get_localized_post_type_slug()` and `Topic::get_localized_taxonomy_slug()`, which resolve the post type's own singular label **in the site's locale**.

On an English site that produces `/event/`. On a German site it produces the German word, and every URL in the table changes with it.

So never hardcode `/event/` in code that has to work on other people's sites. Build URLs from the post type or term, the way WordPress does it:

```php
get_post_type_archive_link( 'gatherpress_event' );
get_permalink( $event_id );
get_term_link( $term, 'gatherpress_topic' );
```

## The `ical` slug is deliberately not translated

Everything else is localizable. `ical` is not: it is a hardcoded constant, `Calendar\Setup::ICAL_SLUG`.

That is on purpose. A calendar subscription URL is stored by the subscriber's calendar application and re-fetched for years. If the slug moved with the site's locale, changing the site language would silently break every subscription that already existed.

## Changing a slug flushes the rewrite rules

The three URL settings declare `'rewrite' => true`. That flag means "this field affects permalink structure", and `Settings::maybe_flush_rewrite_rules()` reads it through `get_rewrite_keys()` and flushes when a flagged field's value actually changes.

So changing a slug in the admin does the right thing on its own. Nobody has to visit the Permalinks screen afterwards.

If you add a setting of your own that affects URL structure, declare the same flag and you inherit the behavior:

```php
'my_custom_url' => array(
	'rewrite' => true,
	// …
),
```

[Extending settings](../settings/extending-settings.md) covers the field definition in full.

> [!IMPORTANT]
> `flush_rewrite_rules()` is expensive and must never run on a normal page load. Flushing on a settings change is correct because it happens once, on an admin action, when something actually changed. Flushing on `init` is the classic version of this mistake and it costs every visitor on every request.

## Adding your own endpoint

The calendar routes are not special-cased anywhere. They are built from a small set of endpoint types, and a companion plugin can register its own the same way: attach an endpoint to a post type's single view, its archive feed, or a taxonomy feed, and either render a template or redirect.

[Custom URL endpoints](../custom-url-endpoints/README.md) is the guide, with the endpoint classes and worked examples.

## When a URL does not resolve

Almost always one of three things:

1. **Rules were not flushed.** Visiting Settings, Permalinks and saving forces a rebuild. If this fixes it permanently, something registered a rewrite without flushing.
2. **Registration order.** Feed endpoints have to be registered before single endpoints, or the rules are saved in an order where the single rule matches first and swallows the feed. `Calendar\Setup` registers them in that order deliberately, and the code says so.
3. **Plain permalinks.** With `permalink_structure` empty, WordPress has no pretty URLs to attach to and none of these routes exist.

To see what actually got registered:

```bash
wp rewrite list --format=csv --fields=match,query | grep ical
```

## See also

- [Custom URL endpoints](../custom-url-endpoints/README.md), adding routes of your own
- [Calendar feeds](../../user/calendar-feeds.md), the user-facing explanation
- [Extending settings](../settings/extending-settings.md), the `rewrite` flag
