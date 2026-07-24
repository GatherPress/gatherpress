# gatherpress_formatted_address


Filters the one-line address label minted from a geocoder result.

Runs after the translatable street/locality format strings have
composed the default label, and receives every raw component so
a callback can rebuild the label wholesale — for example keyed
off `$components['country_code']` when postal conventions should
follow the address's country rather than the site language.

Replaces the `gatherpress_geocode_street_line` filter from
0.34.0, which only exposed the house-number/street portion.

## Auto-generated Example

```php
add_filter(
   'gatherpress_formatted_address',
    function(
        string $label,
        array $components
    ) {
        // Your code here.
        return $label;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$label` Default label (may be empty).
- *`array`* `$components`
  - *`string`* `$house_number` House number.
  - *`string`* `$street` Street name.
  - *`string`* `$name` Feature name (POIs, stations).
  - *`string`* `$locality` City, falling back to district, then county.
  - *`string`* `$region` State/region ('' when it duplicates the locality).
  - *`string`* `$postcode` Postal code.
  - *`string`* `$country` Country name.
  - *`string`* `$country_code` Two-letter country code.

## Files

- [includes/core/classes/class-geocoding.php:1114](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-geocoding.php#L1114)
```php
apply_filters(
				'gatherpress_formatted_address',
				$label,
				array(
					'house_number' => $house_number,
					'street'       => $street,
					'name'         => $name,
					'locality'     => $locality,
					'region'       => $region,
					'postcode'     => $postcode,
					'country'      => $pluck( 'country' ),
					'country_code' => $pluck( 'countrycode' ),
				)
			)
```



[← All Hooks](Hooks.md)
