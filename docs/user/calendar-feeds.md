# Calendar feeds

Subscribe once, never miss an event.

GatherPress publishes subscribable calendar feeds in the widely supported iCalendar (`.ics`) format. Attendees can follow a venue, a topic, or all of a site's events in their own calendar app, and the calendar stays up to date automatically: new events appear, and changes to dates, times, or venues flow through without anyone having to download anything again.

A feed carries each event until it ends. Once an event is over it drops out of the feed, so most calendar apps remove it from the subscriber's calendar the next time they refresh.

Password-protected events are never included in a feed, because anyone with the feed URL can read it. Their Add to Calendar links only work for visitors who have entered the event's password.

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

Replace `example.org` with your site address, and `my-venue` / `my-topic` with the slug of the venue or topic, the same slug that appears in the venue's or topic's own web address. If you have customized the event, venue, or topic permalink bases in the GatherPress settings, the feed URLs follow your custom slugs.

So a community site could offer, for example:

* "Follow everything we do" → `example.org/feed/ical`
* "Only interested in our meetups at the library?" → `example.org/venue/city-library/feed/ical`
* "Just the workshops, please" → `example.org/topic/workshops/feed/ical`

## Subscribing in common calendar apps

* **Google Calendar:** `Other calendars > + > From URL`, then paste the feed URL.
* **Apple Calendar:** `File > New Calendar Subscription…`, then paste the feed URL.
* **Outlook:** `Add calendar > Subscribe from web`, then paste the feed URL.
* **Thunderbird:** `New Calendar > On the Network`, then paste the feed URL.

## Subscribe links

You can turn any link or button into a subscribe link without typing out a feed URL. Set the link's URL to one of the placeholders below, and GatherPress swaps in the right feed URL when the page loads. This works in any block with a link: a Button, a Paragraph, a Navigation link, or an entry in the [Add to Calendar](./blocks/add-to-calendar.md) dropdown.

| Placeholder | Links to |
| --- | --- |
| `#gatherpress-calendar-feed-url` | The feed for the page being viewed: a venue page gets that venue's feed, a topic page that topic's, the events archive the archive feed, and the front page the feed for all events. |
| `#gatherpress-calendar-feed-site-url` | All events on the site. |
| `#gatherpress-calendar-feed-archive-url` | The events archive. On an event, that is the archive the event belongs to. |
| `#gatherpress-calendar-feed-venue-url` | The venue of the event the link sits in, or the venue page being viewed. |
| `#gatherpress-calendar-feed-venue-{slug}-url` | The venue with that slug, for example `#gatherpress-calendar-feed-venue-city-library-url`. |
| `#gatherpress-calendar-feed-topic-{slug}-url` | The topic with that slug, for example `#gatherpress-calendar-feed-topic-workshops-url`. |

Every placeholder also has a `subscribe` form: replace `feed` with `subscribe`, as in `#gatherpress-calendar-subscribe-venue-url`. The `feed` form gives the feed's regular web address, which starts with `https://` on a site served over HTTPS. People can copy and paste it into their calendar app. The `subscribe` form gives the same feed as a `webcal://` address, which most devices open straight in the calendar app and offer to subscribe to. Offering both covers everyone.

The slug is the same one that appears in the venue's or topic's own web address. If you rename the slug later, update any placeholders that use it.

A placeholder that has no feed to point to stays as it is, so the link goes nowhere until there is one. That happens when:

* no venue or topic has the slug;
* the venue is a draft or has a password;
* `#gatherpress-calendar-feed-url` is on a single event, which has a one-time download rather than a feed of its own. Use the venue, topic or site placeholder there instead;
* `#gatherpress-calendar-feed-venue-url` is on an event without a venue, or on an event whose venue is hidden by a password;
* `#gatherpress-calendar-feed-archive-url` is on a page that is not an event or the events archive.

## Automatic discovery

The feeds are also advertised in the site's HTML (as `<link rel="alternate">` tags), so calendar-aware browsers, apps, and services can find the right feed on their own. Visiting a venue page advertises that venue's feed, a topic page advertises that topic's feed, and so on. Often subscribing is as simple as pointing a calendar app at the page URL itself.

## For developers

The feeds are built on GatherPress' custom URL endpoint API, which companion plugins can use to register their own endpoints for any post type or taxonomy. See [Custom URL Endpoints](../developer/custom-url-endpoints/README.md) for the technical details.
