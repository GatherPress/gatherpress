# Event Doors block

Shows when the doors open and close for an event, for example "Doors open 6:30 pm Doors close 11:00 pm".

## Setting the times

The times are not typed into the block. They live on the event itself, in the **Doors open** and **Doors close** fields of the date and time settings in the editor. When the block shows the event you are editing, the same fields also show in the block settings.

Both fields are optional. A time left empty is not shown, and the block shows nothing when neither is set.

## Which day the doors are on

Only a time is saved, so the doors move with the event when its date changes:

* Doors open is on the start date, and Doors close is on the end date.
* A Doors open time later than the start time means the day before.
* A Doors close time earlier than the end time means the day after.
* On an all day event, the doors open on the first day and close on the last one.

When the doors fall on another day than the start or end, the block adds the date so viewers know which night to arrive.

## Display

The times use the time format from settings, and follow the same timezone as the Event Date block. In a Query Loop, each event shows its own times.

The block is hidden wherever the event's date would be, such as a password-protected event before the password is entered.
