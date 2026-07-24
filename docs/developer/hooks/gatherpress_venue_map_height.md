# gatherpress_venue_map_height


Filter the height used when rendering the static venue map.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_height',
    function( int $height ) {
        // Your code here.
        return $height;
    }
);
```

## Parameters

- *`int`* `$height` Default height in pixels.

## Files

- [includes/core/classes/venue/class-map.php:1706](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/class-map.php#L1706)
```php
apply_filters( 'gatherpress_venue_map_height', $default )
```



[← All Hooks](Hooks.md)
