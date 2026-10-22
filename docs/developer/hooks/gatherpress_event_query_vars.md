# gatherpress_event_query_vars


Filters the query vars of a query that lists upcoming or past events.

Lets a plugin narrow or reorder event lists, for example by
location, without replacing the queries that build them. It runs
on `pre_get_posts` for every such list: the Event Query block,
event archives and feeds, the iCal feed, `get_events_list()`, and
the admin's Upcoming and Past views. GatherPress applies the upcoming
or past date range afterwards, so a constraint added here narrows
the list rather than replacing it. Check `$query` to act on only
some lists.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_query_vars',
    function(
        array<string,,
        string $type,
        WP_Query $query
    ) {
        // Your code here.
        return array<string,;
    },
    10,
    3
);
```

## Parameters

- `array<string,` mixed> $query_vars The query's vars, as `WP_Query` reads them. Other variable names: `$query_vars`
- *`string`* `$type` The list type: `upcoming` or `past`.
- *`WP_Query`* `$query` The query being prepared.

## Returns

`array<string,` mixed> The query vars to use.

## Files

- [includes/core/classes/event/class-query.php:356](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/event/class-query.php#L356)
```php
apply_filters(
				'gatherpress_event_query_vars',
				$query->query_vars,
				$events_query,
				$query
			)
```



[← All Hooks](Hooks.md)
