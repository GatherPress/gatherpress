# gatherpress_geocode_query


Filters the address sent to the geocoder.

Runs after the built-in splitting. Use it to add boundaries for a
script the plugin does not know, or to rewrite a local address
convention the geocoder trips over.

## Auto-generated Example

```php
add_filter(
   'gatherpress_geocode_query',
    function(
        string $normalized,
        string $query
    ) {
        // Your code here.
        return $normalized;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$normalized` Address as it will be sent.
- *`string`* `$query` Address as received, postal mark removed.

## Returns

`string` 

## Files

- [includes/core/classes/geocoding/class-query.php:224](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/geocoding/class-query.php#L224)
```php
apply_filters( 'gatherpress_geocode_query', $normalized, $query )
```



[← All Hooks](Hooks.md)
