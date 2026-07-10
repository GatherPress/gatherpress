# gatherpress_geocode_street_line


Filters the street line (house number + street) in a geocode label.

Defaults to "{house number} {street}" (e.g. "42 Hauptstraße").
German-speaking and other locales conventionally place the house
number after the street ("Hauptstraße 42"); both components are
passed so a developer can reorder them to match local convention,
for example by keying off `get_locale()`.

## Auto-generated Example

```php
add_filter(
   'gatherpress_geocode_street_line',
    function(
        string $street_line,
        string $housenumber,
        string $street
    ) {
        // Your code here.
        return $street_line;
    },
    10,
    3
);
```

## Parameters

- *`string`* `$street_line` Default street line ("{house number} {street}").
- *`string`* `$housenumber` House number component (may be empty).
- *`string`* `$street` Street name component (may be empty).

## Files

- [includes/core/classes/class-geocoding.php:1039](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L1039)
```php
apply_filters( 'gatherpress_geocode_street_line', $street_line, $housenumber, $street )
```



[← All Hooks](Hooks.md)
