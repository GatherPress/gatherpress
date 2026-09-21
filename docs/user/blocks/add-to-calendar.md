# Add to Calendar block

Gives visitors a way to put the event into their own calendar. It renders as a dropdown labeled **Add to calendar** with four choices:

- **Google Calendar**, which opens Google Calendar with the event prefilled
- **iCal**, which downloads an `.ics` file
- **Outlook**, the same `.ics` file, named so Outlook picks it up
- **Yahoo Calendar**, which opens Yahoo Calendar with the event prefilled

Google and Yahoo open in a new tab. iCal and Outlook download a file the visitor's calendar application opens.

## Settings

There are none, and that is deliberate. The block reads the event's date, time, time zone, title, description and venue and builds each link itself, so there is nothing to keep in sync by hand. Move it, style it, or remove it.

## Editing it

The block is built from a dropdown with four items inside it, and those inner blocks are ordinary blocks. Double-click into the block and you can rename the button, reword an entry, reorder them, or delete a service your members do not use.

If you change the wording, only the visible text changes. The link behind each entry keeps working, because it is generated from the event rather than typed in.

## Where it appears

It is added to the event template automatically, so a new event usually has one already. You can also insert it yourself anywhere on an event, or inside an [Event Query](./event-query.md) block so every event in a list carries its own.

## Downloading versus subscribing

This block is for **one event**. A visitor gets that event in their calendar, and if you later change the time, their copy does not update.

For a calendar that stays current, point people at a feed instead. A feed is subscribed to once and refreshes on its own, and there are feeds for the whole site, a single venue and a single topic. See [calendar feeds](../calendar-feeds.md).

Both are worth offering. People add one event they are going to; regulars subscribe to the feed.

## See also

- [Calendar feeds](../calendar-feeds.md)
- [Event Date block](./event-date.md)
- [Creating and managing events](../creating-and-managing-events.md)
