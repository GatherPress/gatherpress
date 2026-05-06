# gatherpress_static_map_prewarm_batch_size


Filter the venue-map prewarm scan batch size.

## Auto-generated Example

```php
add_filter(
   'gatherpress_static_map_prewarm_batch_size',
    function( int $size ) {
        // Your code here.
        return $size;
    }
);
```

## Parameters

- *`int`* `$size` Number of posts loaded per batch during prewarm scans.

## Files

- [includes/core/classes/venue/map/class-prewarm.php:128](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-prewarm.php#L128)
```php
apply_filters( 'gatherpress_static_map_prewarm_batch_size', self::SCAN_BATCH_SIZE )
```



[← All Hooks](Hooks.md)
