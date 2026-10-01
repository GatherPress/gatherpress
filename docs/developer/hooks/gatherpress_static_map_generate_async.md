# gatherpress_static_map_generate_async


Filters whether venue-save-triggered static-map generation runs
asynchronously via WP-Cron instead of blocking the save request.

Enabling this trades an immediate map (visible the instant the
save request returns) for a fast save request: the actual
tile-fetch-and-composite work — which can take several seconds
per (zoom, width, height, map_type) combo, twice over when the
retina variant also renders — moves to a WP-Cron job scheduled a
moment later. Particularly useful for bulk venue imports or REST
integrations that create/update many venues in a tight loop.

## Auto-generated Example

```php
add_filter(
   'gatherpress_static_map_generate_async',
    function(
        bool $async,
        int $post_id
    ) {
        // Your code here.
        return $async;
    },
    10,
    2
);
```

## Parameters

- *`bool`* `$async` Whether to defer generation to a cron job. Default false.
- *`int`* `$post_id` Venue post ID.

## Files

- [includes/core/classes/venue/map/class-map.php:596](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/venue/map/class-map.php#L596)
```php
apply_filters( 'gatherpress_static_map_generate_async', false, $post_id )
```



[← All Hooks](Hooks.md)
