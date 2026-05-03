# gatherpress_event_archive_mode


Filters the resolved event archive mode.

Lets plugins override the user-configured mode at runtime —
e.g., force `none` while a maintenance flag is set, or pin
`upcoming` for a specific request context. Returned values
outside the valid set are coerced back to `upcoming`.


`past`, or `none`).

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_archive_mode',
    function( string $mode ) {
        // Your code here.
        return $mode;
    }
);
```

## Parameters

- *`string`* `$mode` The current archive mode (`upcoming`,

## Files

- [includes/core/classes/event/class-setup.php:564](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-setup.php#L564)
```php
apply_filters( 'gatherpress_event_archive_mode', $mode )
```



[← All Hooks](Hooks.md)
