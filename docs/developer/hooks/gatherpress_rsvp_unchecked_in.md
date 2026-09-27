# gatherpress_rsvp_unchecked_in


Fires after an RSVP's check-in has been removed.

## Auto-generated Example

```php
add_action(
   'gatherpress_rsvp_unchecked_in',
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

- [includes/core/classes/rsvp/class-check-in.php:159](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/class-check-in.php#L159)
```php
do_action( 'gatherpress_rsvp_unchecked_in', $rsvp_id )
```



[← All Hooks](Hooks.md)
