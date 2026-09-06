# Venue address format

When you edit a venue, the **Address** field in the Venue Details block offers
autocomplete suggestions from Photon (the OpenStreetMap-based geocoder that
GatherPress proxies). Each suggestion is a one-line, postal-style label such as
`42 Hauptstraße, Berlin, 10115`. Picking a suggestion writes that label into the
venue's address.

The order of the components in that label is locale-aware: GatherPress composes
the label through two translatable format strings, so each language's
translators decide the convention. Sites that need a different policy than
their language's translation provides can rebuild the label with the
[`gatherpress_formatted_address`](#gatherpress_formatted_address) filter.

## Translatable format strings

The label is built from two `sprintf()` format strings, each registered with
`_x()` so translators can reorder the placeholders per locale:

| Context | Default | Placeholders |
|---|---|---|
| `address street line` | `%1$s %2$s` | 1: house number, 2: street name |
| `address locality line` | `%1$s, %2$s, %3$s` | 1: city, 2: region/state, 3: postal code |

The two lines are joined with `, `. With the English defaults a German address
renders as `42 Hauptstraße, Berlin, 10115`; a German translation of `%2$s %1$s`
and `%3$s %1$s` renders the same address as `Hauptstraße 42, 10115 Berlin` —
house number after the street, postal code before the city, region omitted.

Missing components are safe to ignore when translating: GatherPress strips the
dangling separators an empty component leaves behind, so a suggestion without a
house number or postal code still comes out clean. A placeholder a translation
doesn't reference (like the region in `%3$s %1$s`) is simply dropped.

Translations resolve in the locale of the request building the label — for the
editor's autocomplete that is the locale of the user doing the editing, exactly
like every other GatherPress string.

## `gatherpress_formatted_address`

Filters the finished one-line label after the format strings have composed it.
The callback receives every raw component, so it can rebuild the label
wholesale.

| Parameter | Type | Description |
|---|---|---|
| `$label` | `string` | The composed label (may be empty when the geocoder returned no usable components). |
| `$components` | `array` | Raw components, each an empty string when absent: `house_number`, `street`, `name`, `locality`, `region`, `postcode`, `country`, `country_code`. |

The filter returns the label as a string. GatherPress trims the returned value;
a suggestion whose label ends up empty is dropped from the autocomplete list.

Notes on the components:

- `name` is the geocoder feature's display name (a point of interest or a
  station, for example). It becomes the street line when the feature has no
  street data.
- `locality` is the city, falling back to the district and then the county.
- `region` is blanked when it duplicates the locality (city-states like
  Berlin), before either the format strings or this filter see it.

### Key the format off the address's country

Postal conventions follow the country of the address more than the language of
the site. `country_code` (a lowercase two-letter code from the geocoder) makes
that policy possible:

```php
add_filter(
	'gatherpress_formatted_address',
	static function ( string $label, array $components ): string {
		if ( 'de' !== $components['country_code'] ) {
			return $label;
		}

		$street_line   = trim( $components['street'] . ' ' . $components['house_number'] );
		$locality_line = trim( $components['postcode'] . ' ' . $components['locality'] );

		return trim( implode( ', ', array_filter( array( $street_line, $locality_line ) ) ), ', ' );
	},
	10,
	2
);
```

### Reorder the street line only

The equivalent of the removed 0.34.0 filter (see
[Replaces `gatherpress_geocode_street_line`](#replaces-gatherpress_geocode_street_line)):

```php
add_filter(
	'gatherpress_formatted_address',
	static function ( string $label, array $components ): string {
		if ( '' === $components['house_number'] || '' === $components['street'] ) {
			return $label;
		}

		$flipped = $components['street'] . ' ' . $components['house_number'];

		return str_replace(
			$components['house_number'] . ' ' . $components['street'],
			$flipped,
			$label
		);
	},
	10,
	2
);
```

For a single, always-on ordering change, prefer a translation override of the
format strings (a `gettext_with_context` filter or a custom language pack) —
that is what the format strings are for.

## Replaces `gatherpress_geocode_street_line`

The `gatherpress_geocode_street_line` filter shipped in 0.34.0 and only exposed
the house-number/street portion of the label. It was removed in 0.35.0 in favor
of the mechanisms above; a callback attached to it is silently ignored. Port
street-line callbacks to `gatherpress_formatted_address` (previous section) or,
for locale-wide conventions, to a translation of the format strings.

## Unspaced East Asian addresses

Japanese and Chinese addresses are written without spaces, and Korean ones
sometimes arrive that way when pasted. Photon tokenizes on whitespace, so such
an address reaches it as a single token and either matches nothing or matches
the wrong continent: `兵庫県神戸市中央区三宮町3-1-16` resolved to Paris.

Before any request, `Geocoding\Query::normalize()` inserts a space at each
administrative boundary and sends the result. Nothing is removed or reordered
except a Japanese postal mark (`〒650-0021`), which the geocoder cannot use.
Numbers and building names are kept, because they raise precision rather than
lower it: with them the Kobe address above resolves to the named building.

```text
兵庫県神戸市中央区三宮町3-1-16   →  兵庫県 神戸市 中央区 三宮町 3-1-16
北京市朝阳区建国路1号            →  北京市 朝阳区 建国路 1号
대구광역시중구동성로2가          →  대구광역시 중구 동성로 2가
서울특별시 중구 세종대로 110      →  unchanged
```

The top level (prefecture, province, metropolitan city) is matched against a
fixed list rather than by its final character, because those characters also
occur inside names: `京都市` contains `都`, `大구` contains `구`, and `广州市`
contains `州`. A split there sends the geocoder a query that matches nothing.
Lower levels are matched by their closing character, shortest match first, so
`市川市` and `구로구` stay whole.

An address the author already separated is left as it is; only tokens without
whitespace are touched. This applies to both autocomplete and save-time
geocoding, and the split form is what the response cache is keyed on.

### `gatherpress_geocode_query`

Runs after the built-in splitting. Use it to add boundaries for a script the
plugin does not know, or to rewrite a local convention the geocoder trips over.
It receives the split address and the address as received (postal mark already
removed), and its return value is what is sent.

## Scope and limitations

- Formatting applies to the **suggestion label** shown in the editor's address
  autocomplete. That label becomes the saved `gatherpress_address` when a user
  selects a suggestion, so the saved address follows the composed order.
- It does not change the structured address meta (`gatherpress_house_number`,
  `gatherpress_street`, and the rest). Those are stored as separate fields and
  are not reordered.
- An address a user types by hand (rather than choosing a suggestion) is stored
  verbatim and never passes through the format strings or the filter.
- Already-saved addresses are not reformatted when translations or filters
  change — the stored address string stays the single source of truth.
- Splitting is applied to the query only. The address the user typed is what
  gets saved; the spaced form never replaces it.

## See also

- [Hook naming convention](hooks-naming-convention.md)
- The auto-generated per-hook reference under
  [`docs/developer/hooks/`](hooks/) (regenerated by CI on merge).
