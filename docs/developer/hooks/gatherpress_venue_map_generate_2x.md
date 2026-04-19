# gatherpress_venue_map_generate_2x


Filter whether to generate the retina (2×) static-map variant.

Disabling this halves the on-disk footprint at the cost of
losing true retina sharpness on HiDPI displays — the browser
will still upscale the 1× PNG, but labels and road lines will
look softer than a native 2× render. Default true.

## Auto-generated Example

```php
add_filter(
   'gatherpress_venue_map_generate_2x',
    function( bool $enabled ) {
        // Your code here.
        return $enabled;
    }
);
```

## Parameters

- *`bool`* `$enabled` Whether to generate the 2× variant.

## Files

- [includes/core/classes/class-venue-map.php:1276](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-venue-map.php#L1276)
```php
apply_filters( 'gatherpress_venue_map_generate_2x', true )
```



[← All Hooks](Hooks.md)
