# gatherpress_venue_map_zoom


Filter the zoom level used when rendering the static venue map.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_zoom',
    function( int $zoom ) {
        // Your code here.
        return $zoom;
    }
);
```

## Parameters

- *`int`* `$zoom` Default zoom level.

## Files

- [includes/core/classes/class-venue-map.php:1454](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-venue-map.php#L1454)
```php
apply_filters( 'gatherpress_venue_map_zoom', $default )
```



[← All Hooks](Hooks.md)
