# Calendar blocks

GatherPress has four blocks that show the posts of a Query Loop block as a month, week or day calendar. This page is for theme and plugin developers. For the settings in the editor, see the [Event Calendar block](../../user/blocks/event-calendar.md) user guide.

1. [Block hierarchy](#block-hierarchy)
2. [Blocks and attributes](#blocks-and-attributes)
3. [Block variations](#block-variations)
4. [Block binding sources](#block-binding-sources)
5. [How a calendar queries posts](#how-a-calendar-queries-posts)
6. [CSS classes](#css-classes)
7. [Hooks](#hooks)
8. [CSS custom properties](#css-custom-properties)
9. [Content from the GatherPress Calendar plugin](#content-from-the-gatherpress-calendar-plugin)

## Block hierarchy

The "Event Calendar" variation of `core/query` adds this tree:

```text
core/query (Event Calendar variation)
├── core/heading (bound to gatherpress/calendar-heading)
├── core/query-pagination (Calendar Pagination variation)
│   ├── core/query-pagination-previous (Previous Calendar Period variation)
│   └── core/query-pagination-next (Next Calendar Period variation)
└── gatherpress/calendar
    └── gatherpress/calendar-week
        └── gatherpress/calendar-day
            ├── core/paragraph (Calendar Day Number variation)
            └── gatherpress/calendar-entries
                └── gatherpress/modal-manager
                    ├── gatherpress/event-date (start time)
                    ├── core/post-title (opens the modal)
                    └── gatherpress/modal
                        └── gatherpress/modal-content
```

The blocks render on the server. Each calendar block renders its inner blocks itself: one week block for each week row, one day block for each day, and the entry template one time for each post of a day. The editor shows one live day and previews of the other days.

## Blocks and attributes

| Block | Title | Parent | Inner blocks | Block styles |
| --- | --- | --- | --- | --- |
| `gatherpress/calendar` | Calendar | Ancestor: `core/query` | `gatherpress/calendar-week` | `default` (Classic), `minimal`, `bold`, `circular`, `gradient` |
| `gatherpress/calendar-week` | Calendar Week | `gatherpress/calendar` | `gatherpress/calendar-day` | None |
| `gatherpress/calendar-day` | Calendar Day | `gatherpress/calendar-week`, `gatherpress/calendar` | `core/paragraph`, `gatherpress/calendar-entries` | `default` (Classic), `ribbon` |
| `gatherpress/calendar-entries` | Calendar Entries | Ancestor: `gatherpress/calendar-day` | The entry template | `default` (Classic), `dots` (Colored Dots) |

A day renders only its first Day Number paragraph (a paragraph bound to `gatherpress/calendar-day`) and its first Calendar Entries block. Without a Day Number paragraph, it renders the day number in a plain paragraph.

Blocks in the entry template get the `postId` and `postType` of each post as block context, as in a Post Template.

### Attributes of `gatherpress/calendar`

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `viewType` | string | `month` | `month`, `week` or `day`. |
| `unitCount` | number | `1` | Number of months (1 to 12), weeks (1 to 5) or days (1 to 7). |
| `selectedDate` | string | `''` | First month (`YYYY-MM`) or day (`YYYY-MM-DD`). Empty for the current date. |
| `dateModifier` | number | `0` | Date Offset: the number of units from the current date. |
| `showWeekdays` | boolean | `true` | Shows the weekday names. When `false`, the header row has the `gatherpress--screen-reader-text` class. |
| `showWeekends` | boolean | `true` | Shows the weekend days in month and week views. |
| `showScheduled` | boolean | `false` | Adds posts with the `future` status. |
| `dateRangeSource` | string | `default` | `default`, `context` (Current: the post in the block context) or `selected` (Specific: `postId`). |
| `postId` | number | `0` | Source post for `selected`. |
| `sourcePostType` | string | `''` | Post type of the source post. |
| `enableTimeGrid` | boolean | `false` | Shows timed events on an hour timeline. It applies only in week and day views, when the Query orders by `datetime`. |

### Attributes of `gatherpress/calendar-week`

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `dayStartHour` | number | `8` | The hour where the time grid starts, 0 to 23. |
| `dayEndHour` | number | `20` | The hour where the time grid ends, up to 24. The grid has no row for this hour. |

### Attributes of `gatherpress/calendar-day`

| Attribute | Type | Default | Description |
| --- | --- | --- | --- |
| `slotHeight` | number | `48` | Height of one row of the time grid, in pixels. |

In a time grid, each day also gets the `is-style-ribbon` class.

## Block variations

| Block | Variation name | Title |
| --- | --- | --- |
| `core/query` | `gatherpress-calendar` | Event Calendar |
| `core/paragraph` | `gatherpress-calendar-day-number` | Calendar Day Number |
| `core/query-pagination` | `gatherpress-calendar-pagination` | Calendar Pagination |
| `core/query-pagination-previous` | `gatherpress-calendar-pagination-previous` | Previous Calendar Period |
| `core/query-pagination-next` | `gatherpress-calendar-pagination-next` | Next Calendar Period |

The Event Calendar variation queries `gatherpress_event` posts, ordered by `datetime` in ascending order, with enhanced pagination.

## Block binding sources

### `gatherpress/calendar-heading`

Gives the `content` attribute the date range of the calendar in the same Query block. It reads the range from the Query block context, so it shows the range of the page that the visitor is on. In a Query block without a calendar, or outside a Query block, it shows the current month.

```html
<!-- wp:heading {"metadata":{"bindings":{"content":{"source":"gatherpress/calendar-heading"}}}} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->
```

| Range | Example |
| --- | --- |
| One month | October 2026 |
| Months in one year | May – November 2026 |
| Months in two years | Nov 2026 – Feb 2027 |
| One day | Friday, January 8, 2027 |
| Days or weeks in one month | Oct 12 – 18, 2026 |
| Days or weeks in two months | Sep 28 – Oct 4, 2026 |
| Days or weeks in two years | Dec 28, 2026 – Jan 3, 2027 |

The separator comes from the `gatherpress_datetime_separator` filter. Its default for the calendar is "–".

### `gatherpress/calendar-day`

Gives the `content` attribute the day of its calendar day. In an empty cell, before the first day or after the last day of a month, the value is an empty string.

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"gatherpress/calendar-day","args":{"format":"jS"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

The optional `format` argument is a [PHP date format](https://www.php.net/manual/datetime.format.php). Without it, the value is the day of the month. The editor offers these formats in the **Day Number Settings** panel:

| Format | Example |
| --- | --- |
| `j` | 1, 2, 15 |
| `d` | 01, 02, 15 |
| `jS` | 1st, 2nd, 15th |
| `j.` | 1., 2., 15. |
| `D j` | Mon 1, Tue 2 |

## How a calendar queries posts

A Query block becomes a calendar query when it contains a `gatherpress/calendar` block. Query blocks without a calendar do not change.

- The calendar makes one query for its date range. It uses the settings of the Query block, for example the post type and the taxonomy filters. It sets `gatherpress_event_query` to `all`, so the range shows past and upcoming events.
- For a post type with `gatherpress-event-date` support, the date range applies to the event dates. Events that start before the range and end in it are also included. Other posts show on their publish date. See [Post type supports](../post-type-supports/README.md).
- The query gets at most 500 posts. Use the `gatherpress_calendar_posts_per_page` filter to change this number.
- The calendar ignores the offset of the Query block. Its Page Numbers block renders nothing.
- The Previous and Next links use the page parameter of the Query block, for example `query-3-page=2`. Page 1 is the first range, from the date settings of the calendar. Page 2 is the range after it, so a calendar of 3 months moves by 3 months. No page comes before page 1, so the first range has no Previous link.
- In the editor, the preview gets its posts from the REST API with the `gatherpress_calendar_query` parameter. REST requests without this parameter do not change.

The calendar adds `weekendDays`, `postsPerPage`, `startOfWeek` and `timeSlotDuration` to the `gatherpress` key of the block editor settings, so the editor preview uses the same values as the front end.

## CSS classes

### The calendar

The `gatherpress/calendar` wrapper has these classes:

| Class | When |
| --- | --- |
| `gatherpress--is-view-month`, `gatherpress--is-view-week`, `gatherpress--is-view-day` | The view. |
| `gatherpress--has-multiple-units` | More than one month, week or day. |
| `gatherpress--has-time-grid` | The time grid is on. The tables and the week rows also have this class. |

Each month, or each range of weeks or days, is a `<table class="gatherpress-calendar__table">` with a `<caption>` that has the `gatherpress--screen-reader-text` class. In month views, and in week views with more than one week, each row starts with a hidden row header, for example "Week 43".

### Days

| Class | When |
| --- | --- |
| `gatherpress-calendar__day` | All day cells. |
| `gatherpress--is-empty` | A cell before the first day or after the last day of a month. |
| `gatherpress--has-posts` | The day has one or more posts. |
| `gatherpress--has-multi-day-event` | The day has an all-day event or an event of more than one day. |
| `gatherpress--is-weekend` | A weekend day. |
| `gatherpress--is-monday` … `gatherpress--is-sunday` | The weekday. |
| `gatherpress--is-today`, `gatherpress--is-past`, `gatherpress--is-future` | The day compared with today. |

The cell of today also has `aria-current="date"`. The server sets `gatherpress--is-today`, `gatherpress--is-past`, `gatherpress--is-future` and `aria-current` from the date of the site. In the browser, the `gatherpress/calendar-day` store changes them to the date of the visitor, so a cached page stays correct.

### Entries

Each entry is a `<div>` with the classes of `get_post_class()`, for example `post-123` and `type-gatherpress_event`, and these classes:

| Class | When |
| --- | --- |
| `gatherpress-calendar__entry` | All entries. |
| `gatherpress--is-all-day` | An all-day event. |
| `gatherpress--is-multi-day` | An all-day event or an event of more than one day. |
| `gatherpress--is-span-single`, `gatherpress--is-span-start`, `gatherpress--is-span-middle`, `gatherpress--is-span-end` | The part of the event on this day. |
| `gatherpress--is-span-continued` | The event continues from the previous week row. |
| `gatherpress--is-span-continues` | The event continues in the next week row. |
| `gatherpress--has-multi-col-span` | The entry spans two or more columns. |
| `gatherpress--is-timed-event` | The entry is on the time grid. |
| `gatherpress--is-js-hovered`, `gatherpress--is-js-focused` | The pointer or the focus is on an entry of the same event. The calendar styles only `gatherpress--is-js-hovered`. |

Entries also have the data attributes `data-span`, `data-day-index`, `data-total-days`, `data-slot` and `data-row-span`.

## Hooks

The calendar hooks use the `gatherpress_calendar_` prefix. The [hook reference](../hooks/Hooks.md) lists them with their parameters.

### Filters

| Filter | Default | What it changes |
| --- | --- | --- |
| `gatherpress_calendar_weekend_days` | `array( 0, 6 )` | The weekend days, 0 (Sunday) to 6 (Saturday). Values that are not 0 to 6, and repeated days, are dropped. |
| `gatherpress_calendar_posts_per_page` | `500` | The maximum number of posts in one calendar view. Values below 1 become 1. |
| `gatherpress_calendar_time_slot_duration` | `1` | The hours in each labeled row of the time grid. Values below 1 become 1. |

This example makes Friday and Saturday the weekend days:

```php
add_filter(
	'gatherpress_calendar_weekend_days',
	static function (): array {
		return array( 5, 6 );
	}
);
```

### Action

`gatherpress_calendar_entry_render` fires before the calendar renders the entry template of one post. The calendar has added its filters for the entry at that time, and removes them after the entry. The action fires one time for each entry, so an event of more than one day fires it one time for each of its visible days.

Parameters: the post ID, the post type, the parsed blocks of the entry template, and the span data of the entry, for example `span`, `day_index` and `total_days`.

This example shows an untitled event with an empty title, and not "(no title)":

```php
add_action(
	'gatherpress_calendar_entry_render',
	static function (): void {
		remove_filter(
			'the_title',
			array( \GatherPress\Core\Blocks\Calendar_Entries::get_instance(), 'filter_empty_title' ),
			10
		);
	}
);
```

## CSS custom properties

The calendar does not have public CSS custom properties yet. The `--gatherpress-calendar-*` properties in its styles are internal, and they can change. The public properties of GatherPress are in [Theme customizations](../theme-customizations/README.md#css-custom-properties).

## Content from the GatherPress Calendar plugin

The blocks come from the [GatherPress Calendar](https://github.com/carstingaxion/gatherpress-calendar) plugin. The block names, variation names and binding sources did not change, so content saved with the plugin continues to work. A calendar saved with plugin versions 0.1.0 to 0.4.1 has a block deprecation.

The state classes now have the `gatherpress--` prefix, for example `gatherpress--is-today` in place of `is-today`. Update theme CSS that styles the classes of the plugin.

The plugin registers the same blocks, so deactivate it.
