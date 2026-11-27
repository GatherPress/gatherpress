# gatherpress_static_map_delete_unused_delay


Filters how long a replaced static map PNG stays on disk.

A page cache or CDN can still serve HTML that points at the old
file. Return 0 to delete the files at once.

## Auto-generated Example

```php
add_filter(
   'gatherpress_static_map_delete_unused_delay',
    function( int $delay ) {
        // Your code here.
        return $delay;
    }
);
```

## Parameters

- *`int`* `$delay` Seconds before the files are deleted.

## Returns

`int` Seconds before the files are deleted.

## Files

- [includes/core/classes/venue/map/class-saved-image.php:481](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-saved-image.php#L481)
```php
apply_filters( 'gatherpress_static_map_delete_unused_delay', self::DEFAULT_DELETE_DELAY )
```



[← All Hooks](Hooks.md)
