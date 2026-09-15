# gatherpress_rsvp_flag_added


Fires after a flag is added to an RSVP that did not carry it.

Every flag fires this, so code reacting to one flag compares
`$flag` with that flag class's `SLUG`, for example
`Check_In::SLUG` to act on a check-in. It does not fire on a
repeat, a failed write, or when an overlapping request stored
the flag first.

## Auto-generated Example

```php
add_action(
   'gatherpress_rsvp_flag_added',
    function(
        int $rsvp_id,
        string $flag
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$rsvp_id` The RSVP comment ID.
- *`string`* `$flag` The flag slug.

## Returns

`void` 

## Files

- [includes/core/classes/rsvp/flag/class-base.php:143](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/flag/class-base.php#L143)
```php
do_action( 'gatherpress_rsvp_flag_added', $this->rsvp_id, static::SLUG )
```



[← All Hooks](Hooks.md)
