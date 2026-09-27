# Online Event block

Shows the joining link for an online event, and shows it **only to people who said they are attending**.

## What visitors see

It depends on who is looking:

| Who | What they see |
|---|---|
| Someone attending, event not yet over | The joining link |
| Someone who has not RSVPed, or said no | "Online event" with a note that the link is for attendees |
| Anyone, after the event has ended | The note, not the link |

That last row is worth knowing about. The link disappears once the event is over, which keeps an old meeting room out of your published archive.

## Setting the link

The link is not typed into the block. It lives on the event itself, in the **Online event link** field in the event settings panel of the editor. Paste your Zoom, Meet, Jitsi or other URL there and the block picks it up.

Leave the field empty and the block has nothing to show.

## Why it is gated

An open video call link on a public page gets found, and rooms do get crashed. Requiring an RSVP means you know who is joining, and it gives people a reason to RSVP, which makes your numbers more accurate as a side effect.

## Editing it

The block is a container with an icon and the link inside it. Double-click in to reword the label or the attendees-only note, or restyle either piece. The gating is unaffected by anything you change in the editor; it is decided when the page is viewed, based on who is viewing it.

## What you see while editing

In the editor you see the block's full state, including the link, because you are the organizer. That is not what a logged-out visitor sees. To check the real thing, view the event in a private window.

## See also

- [Creating and managing events](../creating-and-managing-events.md)
- [The RSVP system](../rsvp-system.md)
- [Event Date block](./event-date.md), and turning on **Append time zone** for online events
