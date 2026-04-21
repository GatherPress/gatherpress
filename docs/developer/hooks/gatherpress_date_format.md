# gatherpress_date_format

## Auto-generated Example

```php
add_filter(
   'gatherpress_date_format',
    function( $get ) {
        // Your code here.
        return $get;
    }
);
```

## Parameters

- `$get`

## Files

- [includes/core/classes/class-event.php:190](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-event.php#L190)
```php
apply_filters(
			'gatherpress_date_format',
			$settings->get( 'date_format' )
		)
```



[← All Hooks](Hooks.md)
