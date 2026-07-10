# taxonomy_labels_%s

## Auto-generated Example

```php
add_filter(
   'taxonomy_labels_%s',
    function( $default_labels ) {
        // Your code here.
        return $default_labels;
    }
);
```

## Parameters

- `$default_labels`

## Files

- [includes/core/classes/class-topic.php:163](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-topic.php#L163)
```php
apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'taxonomy_labels_%s',
				self::TAXONOMY
			),
			$default_labels
		)
```



[← All Hooks](Hooks.md)
