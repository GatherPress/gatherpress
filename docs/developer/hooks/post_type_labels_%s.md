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

- [includes/core/classes/event/class-setup.php:283](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-setup.php#L283)
```php
apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'post_type_labels_%s',
				Event::POST_TYPE
			),
			$default_labels
		)
```

- [includes/core/classes/venue/class-setup.php:1117](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-setup.php#L1117)
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
