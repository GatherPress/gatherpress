# Event Date block

Shows when an event happens. It is the block that makes an event look like an event, and it is seeded into the event template automatically, so most people meet it already on the page rather than inserting it.

## What it shows

By default, the start and the end, joined by the word "to":

> Saturday, June 14, 2026 6:00 pm to 8:00 pm

Everything about that is adjustable.

## Toolbar

The block toolbar has a **Start** and an **End** toggle, which is the quickest way to change what the block shows without opening the sidebar.

## Display Settings

In the block sidebar:

**Display** chooses what appears:

- Start and end date (the default)
- Start date only
- End date only

**Separator** is the text between the two dates. It is the word "to" unless you change it. A dash, a bullet or an arrow all work, and it is a free text field so anything you type is used as-is.

**Start date format** and **End date format** control how each date is written. Leave them empty to follow the site's own date and time format from Settings, General, which is usually what you want. Fill one in to override it for this block only, using [WordPress date format characters](https://wordpress.org/documentation/article/customize-date-and-time-format/).

Setting a different format for the end date is how you get a compact range, for example a full date at the start and only a time at the end:

> Saturday, June 14, 2026 6:00 pm to 8:00 pm

**Append time zone** adds the event's time zone after the date. Worth turning on for online events, where attendees are reading the page from several time zones and "6:00 pm" alone is genuinely ambiguous.

**Link to event** turns the date into a link to the event. Useful in an event list, where the date is often the thing people click. On the event's own page it links to the page you are already on, so leave it off there.

## Styling

The block supports the usual design tools: text and background color, gradients, link color, typography, spacing, and border. Whatever your theme offers, this block takes.

## In a list of events

Inside an [Event Query](./event-query.md) block, the Event Date shows each event's own date as the list repeats. You do not need to configure anything for that to happen.

## See also

- [Creating and managing events](../creating-and-managing-events.md)
- [Event Query block](./event-query.md)
- [Calendar feeds](../calendar-feeds.md)
