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

- [includes/core/classes/venue/class-map.php:1676](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-map.php#L1676)
```php
apply_filters( 'gatherpress_venue_map_zoom', $default )
```



[← All Hooks](Hooks.md)
