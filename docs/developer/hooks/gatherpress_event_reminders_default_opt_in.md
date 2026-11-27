# gatherpress_event_reminders_default_opt_in


Filters the default state of the event reminders opt-in.

Lets a site that needs explicit consent, for example under the
GDPR, leave reminders off until a user turns them on.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_reminders_default_opt_in',
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

## Returns

`string` Default opt-in state.

## Files

- [includes/core/classes/class-user.php:226](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-user.php#L226)
```php
apply_filters( 'gatherpress_event_reminders_default_opt_in', '1', $user_id )
```



[← All Hooks](Hooks.md)
