# gatherpress_venue_post_type


Filters the post type used as the venue.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_post_type',
    function(
        string,
        string $event_post_type
    ) {
        // Your code here.
        return string;
    },
    10,
    2
);
```

## Parameters

- `string` $post_type       The venue post type slug. Default 'gatherpress_venue'. Other variable names: `Venue::POST_TYPE`
- *`string`* `$event_post_type` The event post type requesting a venue post type.

## Files

- [includes/core/classes/venue/class-setup.php:616](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-setup.php#L616)
```php
apply_filters(
			'gatherpress_venue_post_type',
			Venue::POST_TYPE,
			$event_post_type
		)
```



[← All Hooks](Hooks.md)
