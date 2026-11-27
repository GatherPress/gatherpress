# gatherpress_calendar_posts_per_page


Filters how many posts one calendar view can show, to fit a site with more posts in one view.

Defaults to 500. A calendar queries all posts of its date range in one query, so posts
after this number do not show. Lower it to make the query smaller. Values below 1 become 1.

## Auto-generated Example

```php
add_filter(
   'gatherpress_calendar_posts_per_page',
    function( int $posts_per_page ) {
        // Your code here.
        return $posts_per_page;
    }
);
```

## Parameters

- *`int`* `$posts_per_page` Number of posts to query.

## Returns

`int` Number of posts to query.

## Files

- [includes/core/classes/calendar/class-query-builder.php:117](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/calendar/class-query-builder.php#L117)
```php
apply_filters( 'gatherpress_calendar_posts_per_page', 500 )
```



[← All Hooks](Hooks.md)
