# gatherpress_event_feed_excerpt


Filters the event excerpt in feeds.

Allows themes and plugins to modify the event excerpt before it is included in feeds.
This can be used to add custom formatting, additional event information, or modify
how event excerpts appear in RSS and other feeds.

Example usage:
```php
add_filter( 'gatherpress_event_feed_excerpt', function( $excerpt ) {
    // Add event location to feed excerpt
    $event = new \GatherPress\Core\Event( get_the_ID() );
    $venue = $event->get_venue();
    if ( $venue ) {
        $excerpt .= "\n\nLocation: " . $venue;
    }
    return $excerpt;
} );
```

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_feed_excerpt',
    function( string $excerpt ) {
        // Your code here.
        return $excerpt;
    }
);
```

## Parameters

- *`string`* `$excerpt` The event post excerpt.

## Files

- [includes/core/classes/class-feed.php:279](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-feed.php#L279)
```php
apply_filters( 'gatherpress_event_feed_excerpt', $excerpt )
```



[← All Hooks](Hooks.md)
