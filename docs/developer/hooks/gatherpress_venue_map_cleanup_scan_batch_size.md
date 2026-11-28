# gatherpress_venue_map_cleanup_scan_batch_size


Filters how many venues have their map data read per batch when finding the static maps in use.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_cleanup_scan_batch_size',
    function( int $batch_size ) {
        // Your code here.
        return $batch_size;
    }
);
```

## Parameters

- *`int`* `$batch_size` Number of venues read per batch.

## Files

- [includes/core/classes/venue/map/class-cleanup.php:215](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-cleanup.php#L215)
```php
apply_filters( 'gatherpress_venue_map_cleanup_scan_batch_size', self::SCAN_BATCH_SIZE )
```



[← All Hooks](Hooks.md)
