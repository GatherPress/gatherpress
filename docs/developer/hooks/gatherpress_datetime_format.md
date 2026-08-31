# gatherpress_datetime_format


Filters the format an event's datetime is rendered with.

Applies to every context an event date is shown in, since they
all format through this method: the singular event, an archive,
the Event Date block and a query loop alike.

## Auto-generated Example

```php
add_filter(
   'gatherpress_datetime_format',
    function(
        string $format,
        string,
        bool $local
    ) {
        // Your code here.
        return $format;
    },
    10,
    3
);
```

## Parameters

- *`string`* `$format` The PHP date format.
- `string` $which  Which datetime is being formatted, 'start' or 'end'. Other variable names: `$which`
- *`bool`* `$local` Whether the datetime is rendered in local time rather than GMT.

## Files

- [includes/core/classes/event/class-event.php:551](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L551)
```php
apply_filters( 'gatherpress_datetime_format', $format, $which, $local )
```

- [includes/core/classes/event/class-event.php:725](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L725)
```php
apply_filters( 'gatherpress_datetime_format', $format, $which, $local )
```



[← All Hooks](Hooks.md)
