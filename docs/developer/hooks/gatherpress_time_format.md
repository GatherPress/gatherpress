# gatherpress_time_format

## Auto-generated Example

```php
add_filter(
   'gatherpress_time_format',
    function( $get ) {
        // Your code here.
        return $get;
    }
);
```

## Parameters

- `$get`

## Files

- [includes/core/classes/event/class-event.php:202](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-event.php#L202)
```php
apply_filters(
			'gatherpress_time_format',
			$settings->get( 'time_format' )
		)
```



[← All Hooks](Hooks.md)
