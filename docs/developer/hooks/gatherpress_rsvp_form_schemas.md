# gatherpress_rsvp_form_schemas


Filters the RSVP form schemas about to be stored for a post.

`gatherpress_rsvp_form_schemas` post meta is what every consumer
reads to validate and persist custom fields, but this method is its
only producer and it derives the set from the post's RSVP Form
blocks. A post with no such block resolves to an empty set, which
deletes the meta, so a schema written by anything other than the
block editor is destroyed the next time the post is saved.

This filter is how a consumer keeps its own schemas: return them
alongside, or instead of, the block-derived set. The meta is only
deleted when the filtered result is still empty, so returning
anything non-empty keeps it.

## Example

Keep registration questions stored outside the block editor.

```php
add_filter(
    'gatherpress_rsvp_form_schemas',
    function ( array $schemas, int $post_id ): array {
        $mine = my_plugin_get_registration_schema( $post_id );

        return $mine ? array_merge( $schemas, $mine ) : $schemas;
    },
    10,
    2
);
```

## Parameters

- `array<string,` mixed> $schemas Schemas derived from the post's RSVP Form blocks, keyed by form id. Other variable names: `$schemas`
- *`int`* `$post_id` The post being saved.

## Returns

`array<string,` mixed> The schemas to store, or an empty array to remove the meta.

## Files

- [includes/core/classes/blocks/class-rsvp-form.php:502](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/blocks/class-rsvp-form.php#L502)
```php
apply_filters( 'gatherpress_rsvp_form_schemas', $schemas, $post_id )
```



[← All Hooks](Hooks.md)
