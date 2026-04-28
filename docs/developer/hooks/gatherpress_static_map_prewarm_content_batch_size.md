# gatherpress_static_map_prewarm_content_batch_size


Filter the venue-map prewarm content-scan batch size.

## Auto-generated Example

```php
add_filter(
   'gatherpress_static_map_prewarm_content_batch_size',
    function( int $size ) {
        // Your code here.
        return $size;
    }
);
```

## Parameters

- *`int`* `$size` Number of posts loaded per batch during content scans.

## Files

- [includes/core/classes/venue/map/class-prewarm.php:154](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-prewarm.php#L154)
```php
apply_filters(
			'gatherpress_static_map_prewarm_content_batch_size',
			self::CONTENT_SCAN_BATCH_SIZE
		)
```



[← All Hooks](Hooks.md)
