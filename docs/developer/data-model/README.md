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
| `_gatherpress_rsvp_flag` | RSVP comments | No | Yes/no markers such as `checked-in` and `no-show` |

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
| `gatherpress_rule` | Repeat rules, one meta row per rule. See [Repeat rules](#repeat-rules) |
| `gatherpress_enable_rsvp` | Whether RSVPs are open |
| `gatherpress_enable_anonymous_rsvp` | Whether a person may hide their name |
| `gatherpress_enable_open_rsvp` | Whether logged-out visitors may RSVP by email |
| `gatherpress_capacity` | Capacity. `0` means unlimited |
| `gatherpress_guest_limit` | Guests per RSVP. `0` means none |
| `gatherpress_online_event_link` | The joining link, shown only to attendees |
| `gatherpress_rsvp_form_schemas` | The RSVP form's custom field definitions, keyed by form id |
| `gatherpress_event_reminder_sent` | For each reminder timing, the UTC start it was last queued for, so each timing reminds one start once |

`gatherpress_datetime` is the canonical value the editor and REST write. The [events table](#the-events-table) is derived from it and is what queries read.

The two limit keys are enforced server-side on every write, including from WP-CLI. An RSVP asking for more guests than `gatherpress_guest_limit` allows is not rejected. It is clamped, and the caller still gets a success response.

### Venues

Five keys are editor-writable, registered `show_in_rest` so they can be bound to blocks through `core/post-meta`: `gatherpress_address`, `gatherpress_latitude`, `gatherpress_longitude`, `gatherpress_phone`, `gatherpress_website`.

`gatherpress_manual_coordinates` is an editor-writable boolean. While it is true, the coordinates are set by hand: neither the editor nor the geocode cron overwrites `gatherpress_latitude` and `gatherpress_longitude` from the address.

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

Four keys, all written from the user's own profile screen and all optional:

| Key | Notes |
|---|---|
| `gatherpress_timezone` | Overrides the site timezone when displaying dates to this person |
| `gatherpress_time_format` | Overrides the site time format |
| `gatherpress_event_updates_opt_in` | Whether this person receives event notification email |
| `gatherpress_event_reminders_opt_in` | Whether this person receives reminder email before events they attend |

GatherPress falls back to the site's own settings for any that are unset, so nothing needs to be seeded when a user is created.

Admin list tables also store per-user screen options under the usual `{$screen}_per_page` convention. That is core's mechanism, not GatherPress's, but it is user meta the plugin leaves behind, so uninstall accounts for it.

## The events table

GatherPress creates `{$wpdb->prefix}gatherpress_events` in `Setup::create_tables()` via `dbDelta()`, along with the [date index](#the-date-index):

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

## The date index

`{$wpdb->prefix}gatherpress_occurrence_index` holds one row per occurrence of every event: one row for an event without rules, and one per date for a repeating event. It exists so queries can join on an event's dates like any other row. `Event\Occurrence_Index` owns it.

```sql
CREATE TABLE {$prefix}gatherpress_occurrence_index (
    post_id            bigint(20) unsigned NOT NULL default '0',
    recurrence_id      varchar(15) NOT NULL default '',
    datetime_start     datetime NOT NULL default '0000-00-00 00:00:00',
    datetime_start_gmt datetime NOT NULL default '0000-00-00 00:00:00',
    datetime_end       datetime NOT NULL default '0000-00-00 00:00:00',
    datetime_end_gmt   datetime NOT NULL default '0000-00-00 00:00:00',
    timezone           varchar(64) NOT NULL default '',
    PRIMARY KEY  (post_id,recurrence_id,datetime_start_gmt),
    KEY datetime_start_gmt (datetime_start_gmt),
    KEY datetime_end_gmt (datetime_end_gmt)
);
```

**It is a cache.** The rules and the saved dates are the source of truth, and any event's rows can be rebuilt from them. Nothing keys on a row: per-date pages and RSVPs find a date by its event and key through `Occurrences::find()`, so a date beyond the index still works.

**It stays small.** Rows hold dates only, never post content. Past rows are kept, since they are history. Future rows reach only as far as the horizon, 12 months by default (filter `gatherpress_occurrence_index_horizon`), so an open-ended series never fills the table. The local columns are in each occurrence's own timezone, named in `timezone`.

**It keeps itself current:**

- Saving an event's dates rebuilds its rows, and every rule write saves the dates. A published event with rules rebuilds from today on, since its past can't change.
- Rule meta written directly, such as by an import, rebuilds the event at the end of the request.
- Deleting an event deletes its rows.
- A daily cron moves repeating events' rows forward with the horizon.

**Each site builds its own on its first request** after updating, not on an admin visit: one statement copies every event in from `gatherpress_events`, then events with rules are expanded. `Occurrence_Index::is_ready()` says whether a site's index is built. The table is dropped when a site is deleted and on uninstall.

### Queries list each date

Front-end event queries join the index instead of `gatherpress_events`, so a repeating event comes back once per date: upcoming and past lists, the month and date filters, ordering by date, Query Loops, and the calendar. Pagination counts dates. The index has the events table's columns, so every condition GatherPress builds applies to each date, and a query grouped by post, such as one filtering by several terms, is grouped by date too.

These stay one row per event:

- Queries for IDs (`fields => ids`), since duplicate IDs carry no dates. Reminders, the iCal feed, abilities and `Event\Query::get_events_list()` query IDs.
- The admin, REST requests, feeds, and an event's own page.
- Any query that sets `gatherpress_occurrences` to false. Setting it to true turns the index on where it would otherwise be off.

A one-row-per-event list still reads its upcoming or past filter from the index, since the events table holds only a series' first date: an event is upcoming while any of its dates is, and past once any of them is, so a running series appears in both. Upcoming orders by each event's next date and past by its latest. The admin's Upcoming and Past counts match.

Until a site's index is built, queries keep using `gatherpress_events`.

**Each row knows its date.** A row selects its date as `gatherpress_occurrence_*` properties on the post. While the loop's global `$post` is that row, `Event::get_datetime()` and `Event::is_all_day()` return that date, so the Event Date and Add to Calendar blocks show it. A nested loop or `wp_reset_postdata()` puts the outer row back. `Event::get_saved_datetime()` always returns the saved dates, the series' first date. `Event\Current_Occurrence` reads the row and lets the calendar, which renders entries from plain posts, set an entry's date while it renders.

## Occurrences

An event post is a series, and an occurrence is one of its dates. Without repeat rules, an event is a series of one. `Event::occurrences()` returns the event's `Event\Occurrences`, which is where code asks for its dates:

- `in_range( $from, $to )` returns the `Event\Occurrence` objects that overlap a range, in start order.
- `find( $recurrence_id )` returns one occurrence by its key, or `null`.
- `on_day( $day )` returns the first occurrence that starts on a date in the event's timezone, or `null`.
- `primary()` returns the series' primary occurrence, the one it shows by default: the first that has not ended, preferring one that is not canceled, or the last when all have ended.
- The static `Occurrences::recurrence_id( $start, $is_all_day )` builds a key: the local start as `Ymd\THis` (the RFC 5545 `RECURRENCE-ID` form), or the local date as `Ymd` for an all-day occurrence.

Each occurrence carries its series post ID, its key, its start and end in the event's timezone, whether it lasts all day, and its status (an event status slug, so `is_canceled()` works per date). Today the one occurrence is read from the event's saved dates, in the event's own timezone rather than a viewer's preference, and takes the event's status. The `gatherpress_event_occurrences`, `gatherpress_event_occurrence_by_key` and `gatherpress_event_primary_occurrence` filters let repeat rules supply more dates without callers changing.

### Repeat rules

An event can have `gatherpress_rule` meta: one row per rule, read through `Event::rules()`. Once an event has at least one valid rule, its valid rules decide its dates and the event's saved datetimes are not read for occurrences. A rule with no `rrule` supplies just its one date. An event without valid rules is a series of one, as above.

```php
array(
	'change_id'  => 'b9e2…',                 // the schedule change that created the rule
	'starts_on'  => '2026-12-11',             // local date, always a date of the series
	'ends_on'    => null,                     // local date, inclusive; null means open-ended
	'rrule'      => 'FREQ=MONTHLY;BYDAY=2FR', // the date pattern only; null means only starts_on
	'is_all_day' => false,
	'times'      => array( '20:00' ),         // local times of day; empty when all day
	'timezone'   => 'America/New_York',
	'duration'   => 120,                      // minutes; whole days of 1440 when all day
)
```

`rrule` supports a strict subset of RFC 5545, listed in `Rules::FREQUENCIES` and `Rules::WEEKDAYS`:

- `FREQ`: `DAILY`, `WEEKLY`, `MONTHLY` or `YEARLY`, with an optional `INTERVAL` up to 1000. Weeks start on Monday
- `BYDAY`: for `WEEKLY`, one or more weekdays. For `MONTHLY`, one ordinal weekday, `1` to `4` or `-1` for the last, such as `2FR`
- `BYMONTHDAY`: for `MONTHLY`, `1` to `31`. A month without that day is skipped

A rule ends on `ends_on`, so `UNTIL` and `COUNT` are not accepted, and an occurrence lasts at most 366 days. A part the pattern leaves out comes from `starts_on`: a weekly rule repeats on its weekday, a monthly one on its day of the month. A row that isn't a valid rule, or whose pattern can never produce a date after `starts_on`, is left out. `Rule::rrule()` returns the pattern in one canonical form.

Each occurrence is built from its own local date and time in the rule's timezone, so a clock change on one date never shifts the dates after it. A time in a spring-forward gap starts after the gap, and its key uses that shifted start. An all-day occurrence ends at 23:59:59 local on its last day.

#### Writing rules

Rules change only through three methods on `Event::rules()`, each returning `true` or a `WP_Error` that says why the change was refused:

- `replace( $rows )` replaces every rule. It is for drafts and events that haven't started, and refuses a published or private event whose saved start has passed.
- `change_from( $date, $rows )` changes the schedule from a local `Y-m-d` date on. Rules with dates from `$date` on end the day before it, a rule that starts on or after `$date` is removed, and the new rules must start on or after it. An event without rules keeps its saved date as a rule of that one date, and the change is refused if that date can't be a rule, such as one lasting longer than 366 days. On a published or private event, `$date` must be after today in the event's timezone and in every rule's, so no date that has happened, or is happening today, changes. Changing a single date, such as cancelling tonight's, waits for the exceptions table.
- `stop( $date )` ends every rule the day before `$date`.

```php
$result = $event->rules()->change_from(
	'2027-03-01',
	array(
		array(
			'starts_on' => '2027-03-04',
			'rrule'     => 'FREQ=WEEKLY;BYDAY=TH',
			'times'     => array( '19:00' ),
			'timezone'  => 'America/New_York',
			'duration'  => 90,
		),
	)
);
```

The new rows are validated, stored in canonical form, and share one new `change_id` with the rules the change ends, so a change records what it started and what it stopped. Rows that aren't valid rules, such as one written by a later version, are kept as they were.

After every write, the event's saved datetimes, timezone, all-day flag and the editor's `gatherpress_datetime` move to the series' first occurrence, so the next save of the event keeps them. Lists, calendars, the event page and the editor still read those, so they show a series by its first date, and treat it as past once that date has passed, until dates are expanded in `WP_Query`. What changing an event's date in the editor does once it has rules is decided with the editor UI.

#### Through REST: `gatherpress_repeat`

Every event post type has a `gatherpress_repeat` REST field, which the editor reads with the post and saves with its Save button.

Reading it describes the schedule as one pattern:

- `pattern`: the rule that applies today, or the first one to come. When every rule has ended, the last one.
- `scheduled`: a rule starting after the current one, such as a change from next month.
- `ended`: whether every rule has ended.
- `showable`: whether one pattern can show the schedule. It's false for several rules applying at once, several times of day, or a rule in another timezone than the event.

Each pattern has `starts_on`, `ends_on`, `frequency`, `interval`, `weekdays`, `month_day`, `ordinal_weekday`, `times`, `duration`, `is_all_day` and `timezone`. A password-protected event hides it like its dates.

Writing it takes `{ "pattern": { … } | null, "from": "Y-m-d" }`. A pattern has `frequency`, and optionally `interval`, `weekdays`, `month_day` or `ordinal_weekday`, `ends_on`, `times` and `duration`. The time of day and length default to the event's dates in the same request.

- An event that hasn't started gets its rules replaced, starting on its start date. A null pattern stops it repeating.
- A published event that has started changes from `from`, tomorrow in its timezone by default. The new rule starts on its first matching date on or after `from`, so every other Thursday from a Saturday starts on the next Thursday. A null pattern stops it repeating from `from`.
- A pattern equal to the current rule changes nothing, and one that only moves the end date ends the current rule there.

The save is checked on `rest_pre_insert_{post_type}`, before anything is saved, so a schedule that can't be written fails the request with `gatherpress_invalid_repeat` and leaves the post as it was. Autosaves don't carry the field, and rules aren't revisioned, so a preview or a restored revision doesn't change the schedule.

Direct writes of `gatherpress_rule` through `add_post_meta()`, `update_post_meta()` and `delete_post_meta()` are refused. Writes to a revision, `delete_post_meta_by_key()`, and imports that set `WP_IMPORTING`, such as the WordPress Importer, are let through, and the `gatherpress_rule_meta_write_allowed` filter can allow others. The meta is read-only through REST.

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
- **`/event/rsvp` also takes a plain HTML form.** The RSVP block posts one when JavaScript is unavailable. It sends guests and anonymity as `gatherpress_rsvp_guests` and `gatherpress_rsvp_anonymous`, the block's own field names, and a `gatherpress_redirect` URL on the site. With that URL, the endpoint answers with a 303 back to it instead of JSON, adding `gatherpress_rsvp_error=<event ID>` when the RSVP was not saved, including when the nonce was stale.
- **`/event/nonce` is same-origin only**, checked with `Utility::is_same_origin_request()`. It exists so cached front-end pages can fetch a fresh nonce; it is not a general-purpose token endpoint.
- The event routes are registered from a single array in `Event\Rest_Api::get_event_routes()`. Adding one means adding an entry there, not another `register_rest_route()` call.

GatherPress also extends core's own REST responses rather than replacing them: venue meta is exposed through `register_post_meta( ..., 'show_in_rest' => true )`, so the ordinary `/wp/v2/gatherpress_venue` endpoints already carry it.

## See also

- [Post type supports](../post-type-supports/README.md): putting these features on your own post types
- [RSVP system](../rsvp/README.md): the comment storage, statuses, providers and flags in detail
- [Settings architecture](../settings/architecture.md): where settings are stored and how they travel
- [Hook reference](../hooks/): every filter and action, generated from source
