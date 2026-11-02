# gatherpress_rsvp_flag_removed


Fires after a flag is removed from an RSVP that carried it.

Every flag fires this, so code reacting to one flag compares `$flag`
with that flag class's `SLUG`, for example `Check_In::SLUG` to act
on a removed check-in. It does not fire on a repeat, a failed write,
or when the RSVP itself is deleted.

## Auto-generated Example

```php
add_action(
   'gatherpress_rsvp_flag_removed',
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

- [includes/core/classes/rsvp/flag/class-base.php:217](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/flag/class-base.php#L217)
```php
do_action( 'gatherpress_rsvp_flag_removed', $this->rsvp_id, static::SLUG )
```



[← All Hooks](Hooks.md)
