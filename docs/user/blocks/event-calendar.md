# Event Calendar block

Shows events in a calendar: one or more months, weeks or days. Visitors use the Previous and Next links to go to other dates.

The Event Calendar is a variation of the WordPress Query Loop block. It is not the same as [Add to Calendar](./add-to-calendar.md) or the [calendar feeds](../calendar-feeds.md). Those put events into the calendar app of a visitor.

## Add a calendar

1. In the block inserter, search for "Event Calendar".
2. Add it to a page, a post or a template.

The Event Calendar adds these blocks inside a Query Loop block:

- A heading that shows the dates of the calendar, for example "October 2026".
- The Previous Month and Next Month links.
- The calendar, which is the **Calendar** block.

The calendar shows the events of the current month. It shows past events and upcoming events.

The settings of the Query Loop block apply to the calendar, for example the post type and the filters. The calendar does not use the Event Query settings for upcoming or past events, unfinished events, the offset and the number of events. The editor does not show these settings in a calendar.

## Calendar Settings

Select the Calendar block. The block sidebar shows the **Calendar Settings** panel.

### Date Range Source

The Date Range Source sets the dates that the calendar shows.

- **Default**: the current month, week or day, or a date that you select.
- **Current**: the dates of the event that you edit. This option shows only on an event, or on a post of a different type that has event dates. When you change the event dates, the calendar in the editor changes immediately.
- **Specific**: the dates of an event that you select. Select the event in **Source Post**. The list shows published posts only. If more than one post type has event dates, select the **Post Type** first.

With **Current** or **Specific**, the calendar shows the days of the event. When you select a different event, the editor sets the view from the length of the event: Day for 3 days or less, Week for 28 days or less, and Month for a longer event. You can change the view after that. The settings for the number of units, the date and the Date Offset do not show.

### Calendar View

- **Month**: one or more months.
- **Week**: one or more weeks.
- **Day**: one or more days, side by side.

### Number of months, weeks or days to show

With the Default source, set how many months (1 to 12), weeks (1 to 5) or days (1 to 7) the calendar shows. The label of this setting follows the view, for example **Number of months to show**. The Previous and Next links move by the same number.

When you change the view or this number, the editor also changes the labels of the Previous and Next links, for example to "Next 3 Months". It replaces labels that you typed.

### Current Selection

This box shows the dates that the calendar starts from. For example, it shows "Current Month", or the name and the dates of the selected event.

### Month, Specific Date and Date Offset

These settings show with the Default source.

- In the Month view, select a **Month** and type a **Year** to show that month. In the Week and Day views, select a **Specific Date**.
- **Reset to Current** removes the date. The calendar then follows the current date again.
- When no date is selected, **Date Offset** moves the calendar away from the current date. For example, set **Months from current** to 1 to always show the next month. The value is from -52 to 52. **Reset Offset** sets it to 0.

When you select a date, the Date Offset changes to 0.

### Show Time Grid

**Show Time Grid** shows timed events on a timeline of hours. This setting shows only in the Week and Day views, and only when the Query Loop orders the events by event date. The Event Calendar orders by event date when you add it.

When the time grid is on:

- In List View, select a Calendar Week block. In the **Timeline Hours** panel, set **Day Starts At (Hour)** and **Day Ends At (Hour)**. The default is 8 to 20.
- In List View, select a Calendar Day block. In the **Time Grid Sizing** panel, set **Slot Height (Vertical Space)**, from 32 to 120 pixels. The default is 48.
- All-day events and events of more than one day show as bars above the hours.
- Events that overlap show side by side.
- An event outside the hours shows at the nearest edge of the grid. An event that starts before the first hour starts at the top. An event that starts at or after the last hour shows at the bottom.

### Show Weekdays

Shows the names of the days above the calendar. When it is off, the names do not show on the screen, but screen readers still read them.

### Show Weekends

Shows the weekend days in the Month and Week views. The weekend days are Saturday and Sunday, unless the site changes them.

### Show Scheduled Posts

Adds posts that are scheduled to publish later. All visitors can see what the calendar shows for these posts, for example the title and the excerpt. For visitors, their links work only after the posts are published. Turn this setting on only when that content can be public before the publish date.

## Edit the days

All days of the calendar use the same template. In the editor, one day is the live day, where you edit the blocks. The other days show a preview of the same template, with their own dates and events.

To edit the template on a different day, click that day. With the keyboard, press Tab to go to the day, then press Enter. That day becomes the live day, and the focus moves to it.

The template of a day has two blocks:

- **Day Number**: a paragraph that shows the number of the day. In the **Day Number Settings** panel, select a **Date Format**: 1, 01, 1st, 1. or Mon 1.
- **Calendar Entries**: the events of the day.

A day accepts only paragraphs and Calendar Entries blocks. On the front end, a day shows only its Day Number and its first Calendar Entries block. Do not add other paragraphs.

### Calendar Entries

The Calendar Entries block works like a Post Template block. Its inner blocks are the template for one event. The calendar shows this template one time for each event of the day.

The default template shows the start time and the title of the event. The title opens a popover with the title, the event date, the excerpt and a Close button. You can change the template, for example to add the featured image to the popover.

An all-day event, or an event of more than one day, shows on each of its days.

## Styles

Select a block, then select a style in the **Styles** panel.

### Styles of the Calendar block

- **Classic**: the default style.
- **Minimal**: no background, with thin lines between the rows.
- **Bold**: thick borders and heavy text. Today has the primary color of the theme.
- **Circular**: round days, with large day numbers behind the events.
- **Gradient**: translucent surfaces on a gradient. The colors come from the text color.

### Styles of the Calendar Day block

- **Classic**: the events of a day show one below the other.
- **Ribbon**: an event of more than one day shows as one bar across its days. The bar stays on the same row on each day.

### Styles of the Calendar Entries block

- **Classic**: the time and the title of each event.
- **Colored Dots**: a colored dot for each event, and a colored bar for an event of more than one day. Select a dot or a bar to open the event.

The blocks also have the usual design tools, for example colors, borders and spacing.

## On the front end

- The Previous and Next links change the calendar without a page reload. Then the focus moves to the heading.
- Visitors can go forward from the first dates of the calendar, and back to them, but not to earlier dates. To start the calendar at earlier dates, set a negative Date Offset.
- Today has a mark. The marks for today, past days and future days use the date of the visitor's browser, so a cached page stays correct.
- When the calendar is 600 pixels wide or less, the events show as dots and bars, as in the Colored Dots style. A calendar of one day keeps the event names if the day and the entries use the Classic style and the time grid is off.
- When you point to an event, its title is underlined on all of its days.

## Accessibility

- Each month, or each range of weeks or days, is a table with a caption for screen readers.
- Screen readers announce today as the current date.
- In the Month view, and in a Week view of more than one week, each row has a hidden header with the week number, for example "Week 43".
- When an entry shows only the time, its link also contains the event title for screen readers. For an event of more than one day, screen readers also hear the day, for example "day 2 of 3".
- An event without a title shows "(no title)", so its link has a name.
- In forced colors mode, the dots and bars have a border.
- Each dot has a target area of 24 by 24 pixels.

## Change a list of events into a calendar

A Query Loop block with a Post Template block can become a calendar:

1. Select the Post Template block.
2. In the block toolbar, select the block icon, then select **Calendar**.

The blocks of the Post Template move into the popover of each event. To change the calendar back into a list, transform the Calendar block into a Post Template. The blocks of the popover move back, without the Close button.

## Content from the GatherPress Calendar plugin

The calendar blocks come from the GatherPress Calendar plugin. They use the same block names, so content that you made with the plugin continues to work. Deactivate the plugin, because it adds the same blocks.

## See also

- [Event Query block](./event-query.md)
- [Event Date block](./event-date.md)
- [Calendar feeds](../calendar-feeds.md)
- [Calendar blocks for developers](../../developer/blocks/calendar.md)
