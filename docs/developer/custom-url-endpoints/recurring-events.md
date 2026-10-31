# Recurring events in iCal output

> [!NOTE]
> This is a design reference. GatherPress does not support recurring events yet
> ([#80](https://github.com/GatherPress/gatherpress/issues/80)). Nothing on this
> page ships today. It records how a recurring series should look in the iCal
> download and the subscribable feeds, so the current generator does not make
> that harder, and so the work on #80 has one shape to aim for.

The draft implementation of #80 lives in a contributor fork, as six stacked pull
requests. Part 5,
[e1337geek/gatherpress#10](https://github.com/e1337geek/gatherpress/pull/10),
is the calendar export, and it already follows the shape described here. Names
taken from those drafts are marked *proposed*. They may change before anything
is merged.

## The shape

A recurring series is **one `VEVENT` with a repeat rule**, not one `VEVENT` per
date. A subscriber then sees "every Tuesday at 7pm" as a single series in their
calendar app, and the feed stays the same size however far ahead the series
runs.

| Need | RFC 5545 property | Notes |
|---|---|---|
| The repeat pattern | `RRULE` | Built from the stored rule. One per series. |
| A canceled date | `EXDATE` | One per canceled date, comma separated on one line. |
| One date that differs from the rest | A second `VEVENT` with `RECURRENCE-ID` | Same `UID` as the series. Not part of the first release. |
| An extra date the rule does not produce | `RDATE` | Reserved. Not used for now. |

`RRULE` plus `EXDATE` covers what the first release needs: a pattern, and dates
taken out of it. An extra one-off date is a separate event for now, so `RDATE`
is held back until there is a feature that needs it.

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
7pm local time. The 3 November date is at 00:00 UTC on 4 November, an hour later
in UTC than the October dates. That is correct, and it only works because the
start is written in local time with a `TZID`.

### Rules the output must follow

These come from RFC 5545. Each one is easy to break by accident.

- **Write `DTSTART` in local time with a `TZID`, not in UTC.** RFC 5545 says a
  recurring `DTSTART` should use local time and a time zone reference, so every
  date starts at the same local time (§3.8.5.3). A UTC start makes every date the
  same UTC time, so the local time moves by an hour after a daylight saving
  change.
- **Add one `VTIMEZONE` block per `TZID` used in the file** (§3.6.5). It goes in
  the `VCALENDAR`, once, however many events use that zone.
- **Start the `VEVENT` on the first date the rule produces.** `DTSTART` counts as
  the first instance of the series, and a start the rule would not produce gives
  an undefined result (§3.8.5.3). A series saved on a Wednesday with a rule of
  "every Friday" must start on the first Friday. `DTSTART` always counts
  as the first occurrence, so under `COUNT` a wrong start can push the last real
  date out of the series.
- **Use `UNTIL` or `COUNT`, never both.** When `DTSTART` has a `TZID`, `UNTIL`
  must be a UTC date-time, not a local one (§3.3.10).
- **Match the value type of `DTSTART`** in `RECURRENCE-ID` (required, §3.8.4.4)
  and in `EXDATE` (an `EXDATE` only removes a date when it equals that date's
  start, so in practice it has to match too). A timed series uses
  `EXDATE;TZID=...:20261105T190000`. An all-day series uses
  `DTSTART;VALUE=DATE`, `EXDATE;VALUE=DATE` and an `UNTIL` that is a date, with no
  `TZID`.
- **Keep the `UID` stable.** The series keeps `gatherpress_{post_ID}`. A changed
  single date reuses that `UID` and adds `RECURRENCE-ID` to say which date it
  replaces.
- **Fold every content line at 75 octets**, not only the text properties. An
  `EXDATE` line with many canceled dates goes past the limit quickly.

## Where the data lives

The rule is the source of truth. Dates are worked out from it and stored, and a
cancellation is stored against a date. The rule is never edited to express a
cancellation, so `EXDATE` is always built from the stored dates, not by changing
`RRULE`.

The draft for #80
([e1337geek/gatherpress#6](https://github.com/e1337geek/gatherpress/pull/6))
uses one post per series and stores:

| Data | Where (*proposed*) | Feeds into |
|---|---|---|
| The rule | `gatherpress_recurrence` post meta, a JSON object such as `{"frequency":"weekly","interval":1,"weekdays":[2,4],"end_type":"count","count":6}`. Weekdays run from 0 for Sunday to 6 for Saturday. | `RRULE` |
| Values derived from the rule | `gatherpress_recurrence_*` post meta, for example `gatherpress_recurrence_byday` holding `TU,TH` | `RRULE` |
| One row per date | The `{prefix}gatherpress_event_occurrences` table, with the series post ID, a `recurrence_id` (the local start in `Ymd\THis` form), the start and end in GMT, and a `status` of `scheduled` or `canceled` | `DTSTART`, `EXDATE`, `RECURRENCE-ID` |
| The timezone | The existing event datetime meta, plus a `timezone` column on each occurrence row | `TZID`, `VTIMEZONE` |

Storing `recurrence_id` as the local start in `Ymd\THis` form means it can be
written into `EXDATE` and `RECURRENCE-ID` as it is, next to the series `TZID`.

When a series is split with "Apply going forward", each part is its own post
with its own `UID` and `RRULE`. The earlier part ends before the split date.

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
| GatherPress accepts fixed offsets such as `UTC+5.5` (shown as UTC+5:30) as an event timezone. | A fixed offset has no tz-database name to put in `TZID`. Such an event keeps the UTC form, where `BYDAY` is worked out in UTC and can land on the wrong local weekday near midnight. So a recurring event must use a named timezone, and the editor should not allow a repeat rule on a fixed offset. |
| `get_ical_wrap()` writes `VERSION`, `PRODID` and the events. | It also writes one `VTIMEZONE` per `TZID` found in the events. |
| The `VEVENT` lines are a fixed array in `get_ical_event_string()`. | `RRULE` and `EXDATE` are added after the event lines, and `DTSTART` comes from the first stored date when a rule exists. |
| Only `SUMMARY`, `DESCRIPTION` and `LOCATION` are folded. | Every line is folded. |
| `SEQUENCE` is read from `post_modified_gmt`. | Canceling a date changes the output but may not change the post, so `SEQUENCE` must also move when a date is canceled or restored. Otherwise subscribed calendars keep showing the canceled date. |
| Feeds list events with `event_list_type` set to `upcoming`, which looks at the event's own date. | A series whose first date has passed must stay in the feed while it still has dates to come. |
| The Google and Yahoo links carry one date. | They cannot carry a rule. They keep sending one date, and the iCal and Outlook downloads carry the series. |

### Extension points

Keep the signature of `get_ical_event_string()` as it is. The `External` object
already holds the event, so it can read the rule itself. A `$rrule` parameter
would make every caller find the rule first.

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

## Open questions

These are open on #80 and change parts of the output:

- **Taking one date out of a series to edit it on its own.** This needs the
  `RECURRENCE-ID` override `VEVENT` described above. The first draft does not
  include it.
- **Several times on the same day.** One `RRULE` cannot express "10am and 3pm" with
  different end times. It would need two rules or two series.
- **Downloading one date of a series.** A file holding only a `RECURRENCE-ID`
  component, with no series beside it, is handled differently by different
  calendar apps. Test it before relying on it.

## Related

- [#80: Recurring events](https://github.com/GatherPress/gatherpress/issues/80)
- [#1684: Reserve ICS shape for future RRULE support](https://github.com/GatherPress/gatherpress/issues/1684)
- [RFC 5545: Internet Calendaring and Scheduling (iCalendar)](https://www.rfc-editor.org/rfc/rfc5545)
- [Custom URL endpoints](README.md)
