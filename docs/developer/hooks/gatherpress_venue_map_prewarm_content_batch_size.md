# gatherpress_venue_map_prewarm_content_batch_size


Filter the venue-map prewarm content-scan batch size.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_prewarm_content_batch_size',
    function( int $size ) {
        // Your code here.
        return $size;
    }
);
```

## Parameters

- *`int`* `$size` Number of posts loaded per batch during content scans.

## Files

- [includes/core/classes/class-venue-map-prewarm.php:141](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-venue-map-prewarm.php#L141)
```php
apply_filters(
			'gatherpress_venue_map_prewarm_content_batch_size',
			self::CONTENT_SCAN_BATCH_SIZE
		)
```



[← All Hooks](Hooks.md)
