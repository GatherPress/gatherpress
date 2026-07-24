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

- [includes/core/classes/event/class-setup.php:231](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-setup.php#L231)
```php
apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'post_type_labels_%s',
				Event::POST_TYPE
			),
			$default_labels
		)
```

- [includes/core/classes/venue/class-setup.php:774](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-setup.php#L774)
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
