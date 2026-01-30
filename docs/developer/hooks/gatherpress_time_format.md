# gatherpress_time_format

## Auto-generated Example

```php
add_filter(
   'gatherpress_time_format',
    function( $get_value ) {
        // Your code here.
        return $get_value;
    }
);
```

## Parameters

- `$get_value`

## Files

- [includes/core/classes/class-event.php:185](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event.php#L185)
```php
apply_filters(
			'gatherpress_time_format',
			$settings->get_value( 'general', 'formatting', 'time_format' )
		)
```



[← All Hooks](Hooks)
