# Emails

GatherPress includes an emailing system for event communication.

Emails are sent using WordPress’ mail system (so delivery depends on your hosting and/or an SMTP plugin).

It covers different needs:

* Organizer emails to participants
    * From the event admin UI, organizers can compose a message and email specific groups linked to the event.
    * Typical recipient groups include:
        * All Members: it will email every user of the website (unless they opt-out in their profile for such messages)
        * Attendees (people who RSVPed “yes”)
        * Non-attendees (people who RSVPed “no”)
        * Waiting list (when an event has a limit)
* Site-wide messages to users
    * From **Events → Email** an administrator can message users without tying it to an event. The message can go to:
        * All users
        * Users with one or more chosen roles
        * Active users, who RSVP'd to any event in the past 30, 90, 180 or 365 days (offered while RSVPs are turned on)
    * Delivery runs on cron in batches, so a large user base is not sent in a single request and the page returns right away.
    * Users who turned event updates off in their profile are skipped.
* Waiting list promotions
    * When spots open up on a full event, people on the waiting list move to attending, and each one gets an email confirming their spot.
    * Because it is about their own RSVP, it goes out even if they turned event updates off.
* RSVP confirmation without an account (email handshake)
  See more under the [RSVP system](./rsvp-system.md) section.

![Screenshot of the email sending](./user-doc-media/20260110151117.png)
