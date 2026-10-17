# Data model

Where GatherPress puts things: which post types and taxonomies it registers, which meta keys it writes, the one custom table it creates, and the REST routes it exposes.

This is a reference for reading and extending the data, not a guide to any one feature. For how to put these features on *your own* post types rather than GatherPress's, see [post type supports](../post-type-supports/README.md).

## Post types

| Post type | What it is |
|---|---|
| `gatherpress_event` | An event. Declares `gatherpress-event-date`, `gatherpress-rsvp`, `gatherpress-venue` and `gatherpress-online-event` |
| `gatherpress_venue` | A place. Declares `gatherpress-venue-information` and `gatherpress-venue-map` |

Neither slug is load-bearing. GatherPress checks `post_type_supports()` rather than comparing post types, so a companion plugin's own post type gets the same behavior by declaring the same supports. Code that hardcodes `gatherpress_event` is code that breaks for those users.

## Taxonomies

Two are public. The rest are internal plumbing, hidden from the UI, and are prefixed with an underscore to say so.

| Taxonomy | On | Public | Purpose |
|---|---|---|---|
| `gatherpress_topic` | Events | Yes | Hierarchical topics. The archive slug is the `topics_url` setting |
| `_gatherpress_venue` | Events | No | Ties an event to its venue. One term per published venue post |
| `_gatherpress_rsvp_status` | RSVP comments | No | The response: `attending`, `not_attending`, `waiting_list`, `no_status` |
| `_gatherpress_rsvp_provider` | RSVP comments | No | How the person was identified. One term per provider class |
| `_gatherpress_rsvp_flag` | RSVP comments | No | Yes/no markers such as `checked-in` |

The three RSVP taxonomies are on *comments*, not posts, because an RSVP is a comment. [The RSVP docs](../rsvp/README.md) cover that in full.

`_gatherpress_venue` is not venue-specific machinery. It is produced by the `gatherpress-shadow-source` support, which keeps one hidden term per published post of a given type, in lockstep with its slug and title. Any post type can declare it (productions, organizers, sponsors) and get the same event-tagging behavior. The leading underscore on a term slug (`_my-venue`) is what distinguishes a real shadow term from a sentinel like `online-event`.

## Post meta

### Events

| Key | Notes |
|---|---|
| `gatherpress_datetime` | The canonical JSON blob of start, end and timezone |
| `gatherpress_datetime_start`, `gatherpress_datetime_end` | Local times |
| `gatherpress_datetime_start_gmt`, `gatherpress_datetime_end_gmt` | UTC equivalents |
| `gatherpress_timezone`, `gatherpress_show_timezone` | The event's zone, and whether to print it |
| `gatherpress_is_all_day` | All-day events skip the time entirely |
| `gatherpress_enable_rsvp` | Whether RSVPs are open |
| `gatherpress_enable_anonymous_rsvp` | Whether a person may hide their name |
| `gatherpress_enable_open_rsvp` | Whether logged-out visitors may RSVP by email |
| `gatherpress_capacity` | Capacity. `0` means unlimited |
| `gatherpress_guest_limit` | Guests per RSVP. `0` means none |
| `gatherpress_online_event_link` | The joining link, shown only to attendees |
| `gatherpress_rsvp_form_schemas` | The RSVP form's custom field definitions, keyed by form id |

`gatherpress_datetime` is the canonical value the editor and REST write. The [events table](#the-events-table) is derived from it and is what queries read.

The two limit keys are enforced server-side on every write, including from WP-CLI. An RSVP asking for more guests than `gatherpress_guest_limit` allows is not rejected. It is clamped, and the caller still gets a success response.

### Venues

Five keys are editor-writable, registered `show_in_rest` so they can be bound to blocks through `core/post-meta`: `gatherpress_address`, `gatherpress_latitude`, `gatherpress_longitude`, `gatherpress_phone`, `gatherpress_website`.

Eight more hold the structured address the geocoder derives from `gatherpress_address`: `gatherpress_house_number`, `gatherpress_street`, `gatherpress_city`, `gatherpress_county`, `gatherpress_state`, `gatherpress_postcode`, `gatherpress_country`, `gatherpress_country_code`. These are readable over REST but **not writable**. REST writes are silently stripped, because the geocode cron owns them. Write `gatherpress_address` and let the cron fill the rest.

Both lists exist as constants (`Venue\Meta::EDITOR_WRITABLE_FIELDS` and `::STRUCTURED_ADDRESS_FIELDS`) and are the single source of truth. Read them rather than retyping the keys.

## Comment meta

RSVPs are comments of type `gatherpress_rsvp`. Their extras live in comment meta:

| Key | Notes |
|---|---|
| `gatherpress_rsvp_guests` | Guest count. Deleted rather than set to `0` |
| `gatherpress_custom_<field>` | One row per answered custom field, named from the form schema |
| `gatherpress_rsvp_anonymous` | `1` when the person is hidden from the public list |
| `gatherpress_rsvp_external_id` | The identity value for non-user providers, e.g. an email address |
| `gatherpress_event_updates_opt_in` | Whether this response wants event email |

Custom field answers are covered in [the RSVP docs](../rsvp/README.md#custom-fields-and-the-form-schema), including the schema they are validated against and how a companion plugin supplies one.

The status is **not** in comment meta. It is a term. Reading it with `get_comment_meta()` returns nothing.

## User meta

Three keys, all written from the user's own profile screen and all optional:

| Key | Notes |
|---|---|
| `gatherpress_timezone` | Overrides the site timezone when displaying dates to this person |
| `gatherpress_time_format` | Overrides the site time format |
| `gatherpress_event_updates_opt_in` | Whether this person receives event notification email |

GatherPress falls back to the site's own settings for any that are unset, so nothing needs to be seeded when a user is created.

Admin list tables also store per-user screen options under the usual `{$screen}_per_page` convention. That is core's mechanism, not GatherPress's, but it is user meta the plugin leaves behind, so uninstall accounts for it.

## The events table

GatherPress creates exactly one table, `{$wpdb->prefix}gatherpress_events`, in `Setup::create_tables()` via `dbDelta()`:

```sql
CREATE TABLE {$prefix}gatherpress_events (
    post_id            bigint(20) unsigned NOT NULL default '0',
    datetime_start     datetime NOT NULL default '0000-00-00 00:00:00',
    datetime_start_gmt datetime NOT NULL default '0000-00-00 00:00:00',
    datetime_end       datetime NOT NULL default '0000-00-00 00:00:00',
    datetime_end_gmt   datetime NOT NULL default '0000-00-00 00:00:00',
    timezone           varchar(255) default NULL,
    PRIMARY KEY  (post_id),
    KEY datetime_start_gmt (datetime_start_gmt),
    KEY datetime_end_gmt (datetime_end_gmt)
);
```

**The table does not replace the meta, it serves a different job.** `gatherpress_datetime` post meta is the canonical value: it is what the editor and the REST API write, and it is the representation that travels with the content. The table is derived from it, and exists for querying and scale.

Every event query is a date-range query: upcoming, past, between two dates, ordered by start. `wp_postmeta` stores values as `longtext` with no useful index, so a range comparison means casting every row. Indexed `datetime` columns turn that into a normal range scan, and keep doing so as an install grows.

**The two stay in step automatically.** On `wp_after_insert_post`, `Event\Setup::set_datetimes()` reads the `gatherpress_datetime` meta and writes the table row from it. Update the meta and the table follows; nothing has to write both by hand.

`Event\Query` joins the table into `WP_Query` through the standard SQL clause filters, so the usual query API keeps working and blocks and Query Loops behave normally. Nothing reads the table directly.

The GMT columns are what queries sort and filter on. The local columns exist for display, so a listing does not have to convert every row back.

On multisite the table is per-site: every site in the network gets its own.

## REST endpoints

Everything is under the `gatherpress/v1` namespace, defined as `GATHERPRESS_REST_NAMESPACE`.

| Route | Method | Who can call it |
|---|---|---|
| `/event/rsvp` | POST | Someone who can RSVP to that event |
| `/event/rsvp-form` | POST | Public. This is the open-RSVP path for logged-out visitors |
| `/event/rsvp-status-html` | POST | Someone who can read that event's responses |
| `/event/rsvp-responses` | GET | Someone who can read that event's responses |
| `/event/email` | POST | Someone who can send that event's notifications |
| `/event/nonce` | GET | Same-origin requests only |
| `/venue/{id}/regenerate-map` | POST | Someone who can edit that venue |
| `/geocode` | GET | `edit_posts` |
| `/geocode/search` | GET | `edit_posts` |

A few notes for anyone calling these:

- **`/event/rsvp-form` is deliberately public.** Open RSVP means a visitor who is not logged in can respond, so the permission callback lets everyone through and the validation happens inside. Treat its input as untrusted, because it is.
- **`/event/nonce` is same-origin only**, checked with `Utility::is_same_origin_request()`. It exists so cached front-end pages can fetch a fresh nonce; it is not a general-purpose token endpoint.
- The event routes are registered from a single array in `Event\Rest_Api::get_event_routes()`. Adding one means adding an entry there, not another `register_rest_route()` call.

GatherPress also extends core's own REST responses rather than replacing them: venue meta is exposed through `register_post_meta( ..., 'show_in_rest' => true )`, so the ordinary `/wp/v2/gatherpress_venue` endpoints already carry it.

## See also

- [Post type supports](../post-type-supports/README.md): putting these features on your own post types
- [RSVP system](../rsvp/README.md): the comment storage, statuses, providers and flags in detail
- [Settings architecture](../settings/architecture.md): where settings are stored and how they travel
- [Hook reference](../hooks/): every filter and action, generated from source
