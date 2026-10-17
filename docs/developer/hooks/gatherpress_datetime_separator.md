# gatherpress_datetime_separator


Filter the separator between start and end dates/times.


outside an event.

## Auto-generated Example

```php
add_filter(
   'gatherpress_datetime_separator',
    function(
        string,
        GatherPress\Event $event = null
    ) {
        // Your code here.
        return string;
    },
    10,
    2
);
```

## Parameters

- `string` $separator The separator set on the block, or the translated 'to' when it sets none. Other variable names: `$separator____separator`
- *`GatherPress\Event|null`* `$event` The event, or null where there is none, such as the editor settings

## Files

- [includes/core/classes/event/class-event.php:347](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L347)
```php
apply_filters(
			'gatherpress_datetime_separator',
			'' === $separator ? __( 'to', 'gatherpress' ) : $separator,
			$event
		)
```



[← All Hooks](Hooks.md)
