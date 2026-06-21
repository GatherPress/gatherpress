# gatherpress_shadow_taxonomy_args


Filters the taxonomy registration args for a shadow-source post type.

Gives consumers a hook to tweak labels or other registration args
for the shadow taxonomy without reimplementing the primitive.

## Auto-generated Example

```php
add_filter(
   'gatherpress_shadow_taxonomy_args',
    function(
        array<string, mixed>,
        string $post_type
    ) {
        // Your code here.
        return mixed>;
    },
    10,
    2
);
```

## Parameters

- *`array<string,`* `mixed>` $args      The taxonomy registration args.
- *`string`* `$post_type` The shadow-source post type slug.

## Files

- [includes/core/classes/class-shadow-source.php:250](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-shadow-source.php#L250)
```php
apply_filters( 'gatherpress_shadow_taxonomy_args', $args, $post_type )
```



[← All Hooks](Hooks.md)
