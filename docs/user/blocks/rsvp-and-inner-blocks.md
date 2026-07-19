# RSVP and Inner Blocks

When you create an event, it comes with the RSVP block and its inner blocks listed below. Those blocks are the content of the Modal window that opens when clicking on the RSVP button.

The RSVP block is protected by [Block Guard](README.md#block-guard).

You can see different states of the RSVP block by choosing in the dropdown:

- No response (default): what the user will see before to RSVP
- Attending: what they will see after they RSVP that they will attend
- Waiting List: what they will see if they are on the waiting list
- Not attending: what they will see if they change from Attending to Not Attending, or if an admin changed it for them
- Past event: what they will see for past events

Double-click the block to work inside it, or expand it in the List view and select an inner block directly. Either way you can then edit the inner blocks — for example the content of the Modal windows, such as the buttons. Modify with care.

Note that moving the RSVP block re-protects it, so double-click it again if you want to keep editing inside.


![[../user-doc-media/20260110122237.png]]

## Inner Blocks for No Response, Attending, Waiting List or Not Attending modes

- Modal Manager
    -  Call to Action
        - RSVP Button (shows "RSVP" in No response mode or "Edit RSVP" in Attending/Waiting List/Not attending modes)
    - Group (does not show on No response mode)
        - Row
            - Icon  (by default, it shows the checkmark on Attending more, the clock icon on Waiting list mode, or the X icon on the Not attending mode)
            - RSVP Status (by default, it shows "Attending", "Waiting List" or "Not Attending")
        - RSVP Guest Count Display. Note: if the event is set not to accept guests, this field is greyed out in the editor and will not display on front end.
    - RSVP Modal
        - Modal Content
            - RSVP Heading
            - RSVP Info
            - Form Field
            - Call to Action
                - RSVP Button (by default, it shows "Not Attending"), for the user to change their RSVP
                - Close Button
    - Login Modal (will only show on No Response mode)
        - Modal Content
            - Login Heading
            - Login Info
            - Register Info
            - Call to Action
                - Close Button

## Inner Blocks for Past event

- Call to Action
    - RSVP Button (that will show "Past Event")
