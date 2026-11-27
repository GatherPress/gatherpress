# User roles and permissions

GatherPress relies on WordPress’ default roles and capabilities. It does not introduce a separate permission system.

If you are familiar with how pages, posts, and comments work in WordPress, GatherPress permissions will feel familiar.

## Creating and editing events

Creating and editing events follows the same permission model as pages and posts.

By default, the following roles can create, edit, and publish events:

* Administrator  
* Editor  
* Author

This means:

* Event creation behaves like creating a post or a page.  
* Draft, publish, and update workflows are the same.  
* The block editor experience is identical.  
* Media uploads and block usage follow WordPress rules

Roles that cannot normally create posts (such as Subscriber) cannot create events.

Notes:

* GatherPress uses WordPress’ existing capabilities.  
* Any customization to post permissions (via plugins or custom code) will also affect events.  
* Event ownership matters: authors can edit their own events but not events created by others (unless permissions are extended)

## RSVP permissions

RSVP permissions follow a model similar to commenting in WordPress. RSVPs are stored as comments, so managing them follows the same rule as moderating comments.

What this means:

* RSVPs can be open to everyone, including visitors without an account.  
* Logged-in users can RSVP using their existing account.  
* Anonymous RSVPs are listed without a name. Editors and administrators can still see who they are.  
* Guest RSVPs may collect minimal information (such as email), depending on event settings

Who can manage RSVPs:

Editors and administrators, or anyone else who can moderate comments, can:

* View every RSVP on the Events > RSVPs screen  
* Approve or unapprove RSVPs, and mark them as spam  
* Check in attendees, or mark them as no-shows  
* Delete RSVPs  
* See the names and email addresses behind anonymous RSVPs

Anyone who can edit an event, including the Author who created it, can:

* Add or remove members from the event's attendee list in the editor  
* Email the event's attendees  
* Set the event's capacity and guest limit

Anyone who has RSVPed can change or cancel their own RSVP. Logged-in users do it on the event, and people without an account use the link in their confirmation email.

Notes:

* Approving, checking in, and the Events > RSVPs screen follow the WordPress `moderate_comments` capability, not the right to edit an event. An Author can run their own event's attendee list and emails, but not approve or check in its RSVPs.  
* Plugins that change who can moderate comments also change who can manage RSVPs

## Following WordPress defaults

Because GatherPress follows WordPress defaults:

* Role behavior is predictable.  
* Existing role-management plugins continue to work.  
* Multisite role behavior follows WordPress’ standard rules.  
* Multisite installations follow WordPress’ standard role and capability rules  
* Permissions remain stable across updates

If you need advanced permission control:

* Use WordPress role or membership plugins.  
* GatherPress will respect those rules automatically. 

