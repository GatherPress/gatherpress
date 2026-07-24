# Calendar feeds

Subscribe once, never miss an event.

GatherPress publishes subscribable calendar feeds in the widely supported iCalendar (`.ics`) format. Attendees can follow a venue, a topic, or all of a site's events in their own calendar app, and the calendar stays up to date automatically — new events appear, and changes to dates, times, or venues flow through without anyone having to download anything again.

## Download once vs. subscribe

There are two ways to get GatherPress events into a calendar app, and they behave differently:

* **Add to Calendar** (the [block](./blocks/README.md) on a single event) saves a *one-time snapshot* of that event, or hands it off to Google Calendar or Yahoo Calendar. If the event is rescheduled afterwards, the entry in the attendee's calendar does not update.
* **Calendar feeds** (this page) are a *live subscription*. The calendar app checks the feed URL periodically and keeps every event in it current.

If someone cares about one event, the Add to Calendar block is enough. If they care about your community, hand them a feed.

## Available feeds

Each feed is a URL that can be pasted into any calendar app that supports iCalendar subscriptions:

| Feed | URL |
| --- | --- |
| All events on the site | `example.org/feed/ical` |
| The events archive | `example.org/event/feed/ical` |
| Events at one venue | `example.org/venue/my-venue/feed/ical` |
| Events in one topic | `example.org/topic/my-topic/feed/ical` |

Replace `example.org` with your site address, and `my-venue` / `my-topic` with the slug of the venue or topic — the same slug that appears in the venue's or topic's own web address. If you have customized the event, venue, or topic permalink bases in the GatherPress settings, the feed URLs follow your custom slugs.

So a community site could offer, for example:

* "Follow everything we do" → `example.org/feed/ical`
* "Only interested in our meetups at the library?" → `example.org/venue/city-library/feed/ical`
* "Just the workshops, please" → `example.org/topic/workshops/feed/ical`

## Subscribing in common calendar apps

* **Google Calendar:** `Other calendars > + > From URL`, then paste the feed URL.
* **Apple Calendar:** `File > New Calendar Subscription…`, then paste the feed URL.
* **Outlook:** `Add calendar > Subscribe from web`, then paste the feed URL.
* **Thunderbird:** `New Calendar > On the Network`, then paste the feed URL.

## Automatic discovery

The feeds are also advertised in the site's HTML (as `<link rel="alternate">` tags), so calendar-aware browsers, apps, and services can find the right feed on their own. Visiting a venue page advertises that venue's feed, a topic page advertises that topic's feed, and so on — often subscribing is as simple as pointing a calendar app at the page URL itself.

## For developers

The feeds are built on GatherPress' custom URL endpoint API, which companion plugins can use to register their own endpoints for any post type or taxonomy. See [Custom URL Endpoints](../developer/custom-url-endpoints/README.md) for the technical details.
