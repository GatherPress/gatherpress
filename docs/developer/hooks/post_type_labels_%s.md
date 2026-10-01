# post_type_labels_%s

## Auto-generated Example

```php
add_filter(
   'post_type_labels_%s',
    function( $default_labels ) {
        // Your code here.
        return $default_labels;
    }
);
```

## Parameters

- `$default_labels`

## Files

- [includes/core/classes/event/class-setup.php:275](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-setup.php#L275)
```php
apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'post_type_labels_%s',
				Event::POST_TYPE
			),
			$default_labels
		)
```

- [includes/core/classes/venue/class-setup.php:741](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-setup.php#L741)
```php
apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'post_type_labels_%s',
				Venue::POST_TYPE
			),
			$default_labels
		)
```



[← All Hooks](Hooks.md)
