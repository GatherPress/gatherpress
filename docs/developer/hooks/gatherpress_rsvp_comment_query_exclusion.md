# gatherpress_rsvp_comment_query_exclusion


Filters whether RSVP comments should be excluded from a comment query.

RSVPs are stored as WordPress comments with the `gatherpress_rsvp`
comment_type and excluded from generic comment queries by default so
they don't leak into normal comment lists, feeds, or third-party UIs
that aren't RSVP-aware. Integrations that want the RSVP type to flow
through (federation plugins that filter comment types themselves,
admin reports that intentionally include RSVPs, custom moderation
dashboards) can return false here to skip the default exclusion for
a given query. The filter receives the live `WP_Comment_Query` so
the opt-out can be scoped — e.g. only when the caller's `type__in`
names types the integration owns — rather than disabled globally.

## Auto-generated Example

```php
add_filter(
   'gatherpress_rsvp_comment_query_exclusion',
    function(
        bool $exclude,
        WP_Comment_Query $query
    ) {
        // Your code here.
        return $exclude;
    },
    10,
    2
);
```

## Parameters

- *`bool`* `$exclude` True to apply the RSVP exclusion, false to skip it.
- *`WP_Comment_Query`* `$query` The current comment query (passed by reference upstream).

## Files

- [includes/core/classes/rsvp/class-query.php:266](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/rsvp/class-query.php#L266)
```php
apply_filters( 'gatherpress_rsvp_comment_query_exclusion', true, $query )
```



[← All Hooks](Hooks.md)
