# gatherpress_force_online_event_link


Filters whether to force the display of the online event link.

Allows modification of the decision to force the online event link
display in the `maybe_get_online_event_link` method. Return true to
force the online event link, or false to allow normal checks.

## Auto-generated Example

```php
add_filter(
   'gatherpress_force_online_event_link',
    function( bool $force_online_event_link ) {
        // Your code here.
        return $force_online_event_link;
    }
);
```

## Parameters

- *`bool`* `$force_online_event_link` Whether to force the display of the online event link.

## Returns

`bool` True to force online event link, false to allow normal checks.

## Files

- [includes/core/classes/class-event.php:894](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event.php#L894)
```php
apply_filters( 'gatherpress_force_online_event_link', false )
```



[← All Hooks](Hooks)
