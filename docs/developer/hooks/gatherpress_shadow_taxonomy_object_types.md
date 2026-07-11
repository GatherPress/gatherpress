# gatherpress_shadow_taxonomy_object_types


Filters which event post types the shadow taxonomy should be
attached to.

Default is an empty array — extensions opt in by returning the
event CPTs they want their shadow source linked to (saves
callers from poking `register_taxonomy_for_object_type()`
directly, and surfaces the wiring as a discoverable hook).

Example — companion plugin registers `production` as a
shadow source and wants events tagged with productions:

add_filter(
'gatherpress_shadow_taxonomy_object_types',
function ( $object_types, $source_post_type ) {
if ( 'production' === $source_post_type ) {
$object_types[] = 'gatherpress_event';
}
return $object_types;
},
10,
2
);

## Auto-generated Example

```php
add_filter(
   'gatherpress_shadow_taxonomy_object_types',
    function(
        GatherPress\string[] $object_types,
        string $source_post_type
    ) {
        // Your code here.
        return $object_types;
    },
    10,
    2
);
```

## Parameters

- *`GatherPress\string[]`* `$object_types` Event post types the shadow taxonomy attaches to.
- *`string`* `$source_post_type` Shadow-source CPT slug whose taxonomy is being wired.

## Files

- [includes/core/classes/class-shadow-source.php:190](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-shadow-source.php#L190)
```php
apply_filters(
				'gatherpress_shadow_taxonomy_object_types',
				array(),
				$source_post_type
			)
```



[← All Hooks](Hooks.md)
