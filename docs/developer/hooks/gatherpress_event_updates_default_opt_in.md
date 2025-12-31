# gatherpress_event_updates_default_opt_in


Filters the default state of the event updates opt-in.

This filter allows modification of the default opt-in state for compliance
with regional privacy laws (e.g., GDPR in Germany) that may require
opt-in consent to be unchecked by default.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_updates_default_opt_in',
    function(
        string $string_default_opt_in_default_opt-in_state,
        int $user_id
    ) {
        // Your code here.
        return $string_default_opt_in_default_opt-in_state;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$string_default_opt_in_default_opt-in_state` Other variable names: `$1`
- *`int`* `$user_id` The user ID.

## Files

- [includes/core/classes/class-user.php:174](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-user.php#L174)
```php
apply_filters( 'gatherpress_event_updates_default_opt_in', '1', $user_id )
```



[← All Hooks](Hooks)
