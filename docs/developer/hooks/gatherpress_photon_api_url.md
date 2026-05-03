# gatherpress_photon_api_url


Filters the Photon API base URL used for geocoding and address search.

## Auto-generated Example

```php
add_filter(
   'gatherpress_photon_api_url',
    function( string $url ) {
        // Your code here.
        return $url;
    }
);
```

## Parameters

- *`string`* `$url` Default Photon API URL (e.g. https://photon.komoot.io/api).

## Files

- [includes/core/classes/class-geocoding.php:1122](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L1122)
```php
apply_filters( 'gatherpress_photon_api_url', self::PHOTON_API_URL )
```



[← All Hooks](Hooks.md)
