# gatherpress_datetime_separator


This filter is documented in includes/core/classes/event/class-event.php

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

- `string` $separator The separator set on the block, or the translated 'to' when it sets none. Other variable names: `$separator____separator`, `$separator`
- *`GatherPress\Event|null`* `$event` The event, or null where there is none, such as the editor settings

## Files

- [includes/core/classes/event/class-event.php:332](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L332)
```php
apply_filters(
			'gatherpress_datetime_separator',
			'' === $separator ? __( 'to', 'gatherpress' ) : $separator,
			$event
		)
```

- [includes/core/classes/blocks/event-date/class-html-renderer.php:244](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/event-date/class-html-renderer.php#L244)
```php
apply_filters( 'gatherpress_datetime_separator', $separator, $this->event )
```



[← All Hooks](Hooks.md)
