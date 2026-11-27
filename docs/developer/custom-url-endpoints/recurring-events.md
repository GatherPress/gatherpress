# Recurring events in iCal output

> [!NOTE]
> This is a design reference. GatherPress does not support recurring events yet
> ([#80](https://github.com/GatherPress/gatherpress/issues/80)). Nothing on this
> page ships today. It records how recurring events are being built in core and
> how a recurring series should look in the iCal download and the subscribable
> feeds, so the current generator does not make that harder.

## How recurring events are being built

The model comes from the design discussion on
[#2602](https://github.com/GatherPress/gatherpress/pull/2602#issuecomment-6731427102)
and the
[reply to it](https://github.com/GatherPress/gatherpress/pull/2602#issuecomment-6742119503).
The requirements from events.wordpress.org, which runs the
`gatherpress-recurring-events` plugin (`gpre`) today, are tracked on
[#80](https://github.com/GatherPress/gatherpress/issues/80#issuecomment-6916759622).
Storage formats and table names are not settled, so this page describes the
model, not the schema.

### Vocabulary

- **Series**: the `gatherpress_event` post. Its ID, slug and URL never change,
  whatever happens to its schedule. It holds the shared content: title,
  description and venue.
- **Occurrence**: one date of a series, which is what people RSVP to. A one-off
  event is a series of one.
- **Occurrence key** (`recurrence_id`): the local start in the event's timezone,
  as `Ymd\THis`, or the local date as `Ymd` for an all-day event. This is the
  form events.wordpress.org already uses in URLs such as
  `/event/<slug>/20260904T150000/`. **A key never changes once it exists.** When
  one date moves, it keeps its original key and records the new time, which is
  what `RECURRENCE-ID` means in iCalendar.

### Rules

A series has one or more rules. Each rule has:

- a start date and an optional end date (local dates, the end inclusive)
- a date pattern in `RRULE` form, or none for a single date
- one or more times of day
- a timezone
- a duration
- optionally a venue

Times of day are kept apart from the `RRULE`, because one `RRULE` cannot say
"4:00 PM, 6:30 PM and 9:15 PM": `BYHOUR` and `BYMINUTE` produce every
combination of the two. The timezone belongs to the rule, not the series, so a
series can change timezone from a given date on.

**A schedule change never rewrites the past.** "From here on" ends the current
rules on a date and adds new ones. Rules are otherwise never edited.

The direction is to store rules as post meta on the series, one meta row per
rule, so they travel in WXR exports and reach the editor through
`register_meta()`. The meta key and format are not settled.

### Occurrences

Dates are worked out from the rules for whatever range is being asked for. They
are not stored one row per date. An occurrence is only stored when it has
something of its own:

- a cancellation
- an override (an edited copy of that one date)
- an extra date the rules do not produce
- a moved date, which keeps its original key and holds the new time

The `gatherpress_events` table keeps one row per series. For a recurring series
it holds the span: the start of the first occurrence and the end of the last,
with a far-future end for an open-ended series. Queries keep finding series
through that table, then the dates inside the requested range are expanded in
PHP.

### RSVPs

RSVPs stay comments. A join table links an RSVP comment to its
`(series, recurrence_id)`. A one-off has no join row, so existing RSVPs need no
migration, and someone can RSVP a year out with nothing stored but the RSVP.

### The occurrence API

Code reads dates through a PHP API, not the tables, so table names stay
internal. [#2612](https://github.com/GatherPress/gatherpress/pull/2612) adds the
first part, where every event is a series of one: `Event::occurrences()` returns
an `Occurrences` object with `in_range()`, `find()`, `on_day()` and `primary()`,
and each `Occurrence` has its series ID, key, start and end, whether it is all
day, and whether it is canceled.
[#2602](https://github.com/GatherPress/gatherpress/pull/2602) adds the
`gatherpress_event_datetimes_saved` action, which fires when an event's dates
are saved. Rule read and write, and a current occurrence for per-date work, come
later. The iCal generator should read dates through this API.

### Moving off `gpre`

`gpre` is live on events.wordpress.org, so core has to be an upgrade path from
it. Its keys and URLs are already in the form above, including the per-date
`/ical/`, `/google-calendar/`, `/outlook/` and `/yahoo-calendar/` endpoints, so
nothing published needs to change. Core runs safely while `gpre` is active, and
the migration keeps `gpre`'s tables under new names until the result is checked.

## The iCal shape

Each rule becomes **one repeating `VEVENT` per time of day**, not one `VEVENT`
per date. A subscriber then sees "every Tuesday at 7pm" as a single series in
their calendar app, and the feed stays the same size however far ahead the
series runs. Most series have one rule with one time, so they are one `VEVENT`.

| Model | RFC 5545 | Notes |
|---|---|---|
| A rule's date pattern | `RRULE` | One per `VEVENT`. A single-date rule has none. |
| A rule's time of day | `DTSTART` | Local time with the rule's `TZID`, on the first date the rule produces. |
| A rule's end date | `UNTIL` in the `RRULE` | In UTC, at the end of the last date. An open-ended rule has no `UNTIL`. |
| A canceled occurrence | `EXDATE` | One per canceled date, comma separated on one line. |
| A moved or overridden occurrence | A second `VEVENT` with `RECURRENCE-ID` | Same `UID` as its rule's `VEVENT`. `RECURRENCE-ID` is the original key, the new time goes in `DTSTART`. |
| An extra date | `RDATE`, or its own `VEVENT` | Not decided. |
| A schedule change | The old `VEVENT` gains an `UNTIL`, and a new `VEVENT` starts | Past dates in subscribed calendars do not change. |

Because a moved date keeps its key, its `RECURRENCE-ID` is always the date it
replaces, as the RFC requires.

### Example

A meetup every Tuesday and Thursday at 7pm in New York, six times, starting
Tuesday 27 October 2026. The Thursday on 5 November is canceled. US clocks go
back on 1 November, so the series crosses a daylight saving change.

```text
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Example Site//GatherPress//EN
BEGIN:VTIMEZONE
TZID:America/New_York
BEGIN:DAYLIGHT
TZOFFSETFROM:-0500
TZOFFSETTO:-0400
TZNAME:EDT
DTSTART:20260308T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:-0400
TZOFFSETTO:-0500
TZNAME:EST
DTSTART:20261101T020000
RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
URL:https://example.org/event/downtown-meetup/
STATUS:CONFIRMED
DTSTART;TZID=America/New_York:20261027T190000
DTEND;TZID=America/New_York:20261027T210000
DTSTAMP:20261020T120000Z
LAST-MODIFIED:20261020T120000Z
SEQUENCE:214660800
SUMMARY:Downtown Meetup
RRULE:FREQ=WEEKLY;BYDAY=TU,TH;COUNT=6
EXDATE;TZID=America/New_York:20261105T190000
UID:gatherpress_123
END:VEVENT
END:VCALENDAR
```

The calendar app shows 27 and 29 October, then 3, 10 and 12 November, all at
7pm local time. `COUNT` includes the canceled date, so six dates become five.
The 3 November date is at 00:00 UTC on 4 November, an hour later in UTC than the
October dates. That is correct, and it only works because the start is written
in local time with a `TZID`.

### Rules the output must follow

These come from RFC 5545. Each one is easy to break by accident.

- **Write `DTSTART` in local time with a `TZID`, not in UTC.** A recurring
  `DTSTART` should use local time and a time zone reference, so every date starts
  at the same local time (§3.8.5.3). A UTC start makes every date the same UTC
  time, so the local time moves by an hour after a daylight saving change.
- **Add one `VTIMEZONE` block per `TZID` used in the file** (§3.6.5). It goes in
  the `VCALENDAR`, once, however many events use that zone. A series whose rules
  use two timezones needs both.
- **Start each `VEVENT` on the first date its rule produces.** `DTSTART` counts
  as the first instance, and a start the rule would not produce gives an
  undefined result (§3.8.5.3). A rule starting on a Wednesday with a pattern of
  "every Friday" starts on the first Friday.
- **Use `UNTIL` or `COUNT`, never both.** When `DTSTART` has a `TZID`, `UNTIL`
  must be a UTC date-time, not a local one (§3.3.10). Rules end on a date, so
  core writes `UNTIL`.
- **Match the value type of `DTSTART`** in `RECURRENCE-ID` (required, §3.8.4.4)
  and in `EXDATE` (an `EXDATE` only removes a date when it equals that date's
  start, so in practice it has to match too). A timed series uses
  `EXDATE;TZID=...:20261105T190000`. An all-day series uses
  `DTSTART;VALUE=DATE`, `EXDATE;VALUE=DATE` and an `UNTIL` that is a date, with no
  `TZID`. The occurrence key is already in these forms, so it can be written as
  it is.
- **Give each repeating `VEVENT` its own stable `UID`.** Two `VEVENT`s with the
  same `UID` and no `RECURRENCE-ID` are not allowed, so a series with two rules,
  or one rule with two times, needs one `UID` for each. A one-off and a
  single-rule, single-time series keep `gatherpress_{post_ID}`, so existing
  subscriptions are not duplicated.
- **Fold every content line at 75 octets.** The generator already does this. An
  `EXDATE` line with many canceled dates goes past the limit quickly.

## What the current generator needs

The iCal output today comes from
[`External::get_ical_event_string()`](../../../includes/core/classes/calendar/external/class-external.php)
for one event, and
[`Setup::get_ical_list()`](../../../includes/core/classes/calendar/external/class-setup.php)
plus `Setup::get_ical_wrap()` for feeds. It emits one `VEVENT` per event. That
is right for single events, but these parts assume one event is one date:

| Today | What recurrence needs |
|---|---|
| Timed events are written in UTC: `DTSTART:20261027T230000Z`. | A named-timezone event writes `DTSTART;TZID=...` in local time. Single events land at the same moment either way, so this can change for every event, not only recurring ones. |
| An event timezone can be a fixed offset, stored as `+05:30` once #2602 lands. | A fixed offset has no tz-database name to put in `TZID`, so such an event keeps the UTC form, where `BYDAY` is worked out in UTC and can land on the wrong local weekday near midnight. A rule must use a named timezone, and the editor should not allow a repeat pattern on a fixed offset. |
| `get_ical_wrap()` writes `VERSION`, `PRODID` and the events. | It also writes one `VTIMEZONE` per `TZID` found in the events. |
| `get_ical_event_string()` returns one `VEVENT` built from the event's own dates. | It returns one `VEVENT` per rule and time of day, plus one per moved or overridden occurrence, with dates from the occurrence API. |
| `SEQUENCE` is read from `post_modified_gmt`. | Canceling or moving a date changes the output but may not change the post, so `SEQUENCE` must also move when an occurrence changes. Otherwise subscribed calendars keep showing the old date. |
| Feeds list events with `event_list_type` set to `upcoming`, which looks at the event's row in `gatherpress_events`. | That row holds the series span, so a series stays in the feed while it has dates to come. The query does not need to change. |
| The per-event download is the whole series. | events.wordpress.org already serves a file per date (`/event/<slug>/<Ymd\THis>/ical/`). That file needs `RECURRENCE-ID`, or a calendar app may treat it as replacing the whole series, which is what `gpre`'s per-date file risks today by reusing the series `UID` without one. |
| The Google and Yahoo links carry one date. | They cannot carry a rule. They keep sending one date, the occurrence's, and the iCal and Outlook downloads carry the series. |

### Extension points

Keep the signature of `get_ical_event_string()` as it is. The `External` object
already holds the event, so it can read the rules and occurrences itself.

When the recurrence lines land, add one filter over the finished list of
`VEVENT` property lines, so a companion plugin can add, change or remove lines
without copying the method. Under the
[hook naming convention](../hooks-naming-convention.md) it belongs to the
`calendar_external` area, for example
`gatherpress_calendar_external_vevent_properties`, passing the lines and the
event post ID. A rule-only filter such as `gatherpress_event_ical_rrule` is
narrower and does not follow the convention.

## Alternatives considered

- **One `VEVENT` per date, worked out on the server.** Simple, but the feed grows
  with every date in the horizon, an open-ended series has no natural end, and
  calendar apps show unrelated events instead of one series.
- **`RDATE` listing every date.** Valid, but it is the same list of dates in one
  line, with the same growth, and it cannot describe an open-ended series.
- **One `RRULE` holding the times as `BYHOUR` and `BYMINUTE`.** It produces every
  combination of hours and minutes, so it cannot express showtimes like 4:00 PM
  and 6:30 PM. That is why times of day are kept apart from the pattern.

## Open questions

These are open on #80 and change parts of the output:

- **The `UID` form for a series with several rules or times.** It has to stay
  the same for as long as the rule exists.
- **Extra dates.** An `RDATE` on the rule's `VEVENT`, or a `VEVENT` of their own.
- **Downloading one date of a series.** A file holding only a `RECURRENCE-ID`
  component, with no series beside it, is handled differently by different
  calendar apps. Test it before relying on it.
- **Corrections that touch past dates.** A timezone fixed with the same
  wall-clock time keeps every key. Whether such a correction may change past
  occurrences, and so their `VEVENT`s, is not decided.

## Related

- [#80: Recurring events](https://github.com/GatherPress/gatherpress/issues/80)
- [#2602: Datetimes-saved action and timezone normalization](https://github.com/GatherPress/gatherpress/pull/2602), with the design discussion
- [#2612: Occurrence API](https://github.com/GatherPress/gatherpress/pull/2612)
- [#1684: Reserve ICS shape for future RRULE support](https://github.com/GatherPress/gatherpress/issues/1684)
- [RFC 5545: Internet Calendaring and Scheduling (iCalendar)](https://www.rfc-editor.org/rfc/rfc5545)
- [Custom URL endpoints](README.md)
