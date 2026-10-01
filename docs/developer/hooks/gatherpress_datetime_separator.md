# gatherpress_datetime_separator


Filter the separator between start and end dates/times.

## Auto-generated Example

```php
add_filter(
   'gatherpress_datetime_separator',
    function(
        string $default_separator,
        GatherPress\Event $event
    ) {
        // Your code here.
        return $default_separator;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$default_separator` The separator string.
- *`GatherPress\Event`* `$event` The event instance.

## Files

- [includes/core/classes/event/class-event.php:284](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L284)
```php
apply_filters(
			'gatherpress_datetime_separator',
			$default_separator,
			$this
		)
```



[← All Hooks](Hooks.md)
