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

RSVP permissions follow a model similar to commenting in WordPress.

What this means:

* RSVPs can be open to everyone, including visitors without an account.  
* Logged-in users can RSVP using their existing account.  
* Anonymous RSVPs (listed without a name) are still visible to event organizers.  
* Guest RSVPs may collect minimal information (such as email), depending on event settings

Who can manage RSVPs:

* Administrators  
* Editors  
* Users who have permission to edit the event  
* Subscribers can edit or cancel their own RSVP

These users can:

* View RSVP lists (event editors only)  
* Unapprove RSVPs (event editors only)  
* Approve or unapprove RSVPs (event editors only)  
* Manage attendance limits (event editors only)  
* Edit or cancel their own RSVP (subscribers and logged-in users)

Notes:

* RSVP permissions are tied to event editing rights, not a separate role.  
* Changing who can edit an event directly affects who can manage its RSVPs

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

