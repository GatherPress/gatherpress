# Subscribe to Events block

The _Subscribe to Events_ block hands visitors a link they can paste into a calendar app to follow your events. It is the editor-facing front end for [Calendar feeds](../calendar-feeds.md): instead of telling people to copy a URL out of a support page, you place the link wherever it makes sense on the site.

## Difference from Add to Calendar

The two blocks look similar but do different jobs:

- [Add to Calendar](./README.md) sits on a single event. It saves a one-time copy of that event, or opens Google or Yahoo Calendar with the event filled in. If the event is later rescheduled, the visitor's calendar entry is not updated.
- **Subscribe to Events** produces a _live subscription_. The calendar app re-reads the feed on its own schedule, so new events appear and changed dates flow through with nothing for the visitor to do.

Use Add to Calendar when a visitor cares about one event. Use Subscribe to Events when they care about your community.

## Picking a scope

The block decides which events the feed contains. In the block sidebar, choose one of:

- **All events on the site**: the whole events feed, equivalent to `example.org/feed/ical`. This is the default, and it works with no further setup.
- **An events archive**: the feed for a post type's archive. If your site registers more than one event post type, pick which one; leaving it on the default uses the primary event post type.
- **Events at one venue**: start typing a venue name and pick it from the suggestions. The feed then contains only events held at that venue.
- **Events in one topic**: the same idea for a topic.

The venue and topic pickers only appear for their own scope. If a scope needs an ID and none is selected yet, the block renders nothing in the editor preview and on the front end. Pick a venue or topic and the links appear.

## Link format

A feed can be offered in two flavors, and the **Links to show** setting controls which are shown:

- **iCal link only**: a plain `https://` link. This is what most calendar apps want when the visitor pastes or imports a URL.
- **Subscribe link only**: the same feed with a `webcal://` address. Clicking a `webcal:` link hands the URL straight to the visitor's default calendar app, which is usually the smoother path on desktop.
- **iCal and subscribe links**: the default. Both links are listed.

Each link has a sensible default label ("iCal feed" and "Subscribe") and each can be renamed with the **iCal link text** and **Subscribe link text** fields, so you can phrase them for your audience.

## Styling

The block outputs an unstyled list of links that follows the site's theme. Color, spacing, and typography controls are available in the block sidebar like any other block, and both links inherit the theme's link color.

## For developers

The feed URLs come from the same resolver the rest of GatherPress uses, so anything you can filter there is reflected here. See [Calendar feeds](../calendar-feeds.md#for-developers) and [Custom URL Endpoints](../../developer/custom-url-endpoints/README.md).
