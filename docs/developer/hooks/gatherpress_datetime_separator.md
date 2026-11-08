# gatherpress_datetime_separator


Filter the separator between start and end dates/times.


outside an event.

## Auto-generated Example

```php
add_filter(
   'gatherpress_datetime_separator',
    function(
        $separator,
        GatherPress\Event $event = null
    ) {
        // Your code here.
        return $separator;
    },
    10,
    2
);
```

## Parameters

- `$separator` Other variable names: `string     $separator The separator set on the block, or the translated 'to' when it sets none.`, `$separator____separator`
- *`GatherPress\Event|null`* `$event` The event, or null where there is none, such as the editor settings

## Files

- [includes/core/classes/blocks/event-date/class-html-renderer.php:262](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/event-date/class-html-renderer.php#L262)
```php
apply_filters( 'gatherpress_datetime_separator', $separator, $this->event )
```

- [includes/core/classes/event/class-event.php:342](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L342)
```php
apply_filters(
			'gatherpress_datetime_separator',
			'' === $separator ? __( 'to', 'gatherpress' ) : $separator,
			$event
		)
```



[← All Hooks](Hooks.md)
