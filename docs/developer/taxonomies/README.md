# Taxonomies

GatherPress registers one public taxonomy of its own, **Topics** (`gatherpress_topic`), and expects you to add your own alongside it. Nothing here needs a filter GatherPress invented: taxonomies are WordPress's own extension point, and this guide is mostly about the one thing that is GatherPress-specific — which post types a taxonomy should be attached to, and when.

## Topics follow post type support, not a post type

Topics is registered for every post type declaring [`gatherpress-event-date`](../post-type-supports/README.md#gatherpress-event-date), the same support that gives a post type datetimes, RSVPs and calendar feeds. Declare that support and your post type is taggable with Topics with no further wiring:

```php
register_post_type( 'my_custom_event', array(
    'supports' => array( 'title', 'editor', 'gatherpress-event-date' ),
    // ... other args
) );
```

Two details matter if you are registering your own taxonomy the same way.

**Register at `init` priority 11.** `get_post_types_by_support()` can only see post types that are already in the registry, and most plugins and themes register theirs at `init` priority 10. GatherPress registers Topics at priority 11 for exactly this reason.

**Catch late registrations too.** A post type registered after your sweep — at a later priority, or outside `init` altogether — will not be in the list. Listen for `registered_post_type` and pair it then. GatherPress does both; the sweep and the listener together mean order does not matter.

## Adding your own taxonomy across event post types

```php
add_action(
    'init',
    function (): void {
        register_taxonomy(
            'my_event_format',
            get_post_types_by_support( 'gatherpress-event-date' ),
            array(
                'label'        => __( 'Formats', 'my-plugin' ),
                'hierarchical' => false,
                'public'       => true,
                'show_in_rest' => true,
            )
        );
    },
    11
);

add_action(
    'registered_post_type',
    function ( string $post_type ): void {
        if ( ! taxonomy_exists( 'my_event_format' ) || ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
            return;
        }

        register_taxonomy_for_object_type( 'my_event_format', $post_type );
    }
);
```

`hierarchical => true` gives you a Topics-style checkbox tree with parents; `false` gives a flat tag input. That choice also decides the REST and block editor controls you get, so make it before you have terms.

`show_in_rest => true` is what makes the taxonomy appear in the block editor and in the Query Loop's taxonomy filters. Leave it off and your taxonomy is admin-only.

## Attaching WordPress's built-in taxonomies

Categories and tags are not attached to `gatherpress_event` out of the box. If you want them:

```php
add_action(
    'init',
    function (): void {
        foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
            register_taxonomy_for_object_type( 'category', $post_type );
            register_taxonomy_for_object_type( 'post_tag', $post_type );
        }
    },
    11
);
```

## Removing Topics from a post type

Topics arrives with `gatherpress-event-date`, but the pairing is separable from the support. To keep the datetimes and drop the taxonomy:

```php
add_action(
    'init',
    function (): void {
        unregister_taxonomy_for_object_type( 'gatherpress_topic', 'my_custom_event' );
    },
    12
);
```

Priority 12 so it runs after GatherPress's priority-11 registration. Unregistering the taxonomy outright (`unregister_taxonomy()`) is a heavier hammer than it looks: the Events settings screen reads its labels, and the topic archive and calendar feeds query it.

## Renaming Topics

Topics is renameable through WordPress's own label filter, no GatherPress hook required:

```php
add_filter(
    'taxonomy_labels_gatherpress_topic',
    function ( object $labels ): object {
        $labels->name          = __( 'Types', 'my-plugin' );
        $labels->singular_name = __( 'Type', 'my-plugin' );

        return $labels;
    }
);
```

This changes more than the admin menu. `Topic::get_localized_taxonomy_slug()` reads the same filter to derive the default permalink base, so a renamed taxonomy also proposes a matching URL — `/type/…` rather than `/topic/…` — as the default of the **Permalink base** field under Settings → Events. The default only applies to a site that has not set that field; an existing site keeps whatever base it saved, and you change it there.

The singular name is what the slug is derived from, and it is sanitized with `sanitize_title()`, so a label with accents or spaces still yields a usable URL.

## Term colors and other add-ons

Term-level presentation — colors, icons, ordering — is add-on territory rather than something GatherPress builds in. [gatherpress-taxonomy-colors](https://github.com/carstingaxion/gatherpress-taxonomy-colors) is the existing example, and it works against any taxonomy attached to an event post type, including your own.

## Related

- [Post type supports](../post-type-supports/README.md) — what `gatherpress-event-date` gives a post type
- [Hook reference](../hooks/) — every hook GatherPress exposes
- WordPress: [`register_taxonomy()`](https://developer.wordpress.org/reference/functions/register_taxonomy/), [`register_taxonomy_for_object_type()`](https://developer.wordpress.org/reference/functions/register_taxonomy_for_object_type/)
