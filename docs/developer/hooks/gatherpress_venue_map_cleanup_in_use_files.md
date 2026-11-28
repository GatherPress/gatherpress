# gatherpress_venue_map_cleanup_in_use_files


Filters the list of in-use static-map image filenames before garbage collection.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_cleanup_in_use_files',
    function( array<string, bool> ) {
        // Your code here.
        return bool>;
    }
);
```

## Parameters

- *`array<string,`* `bool>` $in_use Map of in-use image basenames.

## Files

- [includes/core/classes/venue/map/class-cleanup.php:261](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-cleanup.php#L261)
```php
apply_filters( 'gatherpress_venue_map_cleanup_in_use_files', $in_use )
```



[← All Hooks](Hooks.md)
