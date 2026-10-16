# gatherpress_rsvp_checked_in


Fires after an RSVP has been checked in.

## Auto-generated Example

```php
add_action(
   'gatherpress_rsvp_checked_in',
    function( int $rsvp_id ) {
        // Your code here.
    }
);
```

## Parameters

- *`int`* `$rsvp_id` The RSVP comment ID.

## Returns

`void` 

## Files

- [includes/core/classes/rsvp/class-check-in.php:116](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/class-check-in.php#L116)
```php
do_action( 'gatherpress_rsvp_checked_in', $rsvp_id )
```



[← All Hooks](Hooks.md)
