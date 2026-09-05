# gatherpress_geocode_languages


Filters the languages the geocoder is willing to be asked for.

Widen this when `gatherpress_photon_api_url` points at a Photon
instance that serves more languages than the public one, so a site
gets results named in its own locale instead of falling back.

## Auto-generated Example

```php
add_filter(
   'gatherpress_geocode_languages',
    function( GatherPress\string[] $languages ) {
        // Your code here.
        return $languages;
    }
);
```

## Parameters

- *`GatherPress\string[]`* `$languages` Language codes the geocoder accepts.

## Returns

`GatherPress\string[]` 

## Files

- [includes/core/classes/class-geocoding.php:1296](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L1296)
```php
apply_filters( 'gatherpress_geocode_languages', self::PHOTON_LANGUAGES )
```



[← All Hooks](Hooks.md)
