# gatherpress_event_feed_content


Filters the event content in feeds.

Allows themes and plugins to modify the event content before it is included in feeds.
This can be used to add custom formatting, additional event information, or modify
how event content appears in RSS and other feeds.

Example usage:
```php
add_filter( 'gatherpress_event_feed_content', function( $content ) {
    // Add event location to feed content
    $event = new \GatherPress\Core\Event( get_the_ID() );
    $venue = $event->get_venue();
    if ( $venue ) {
        $content .= "\n\nLocation: " . $venue;
    }
    return $content;
} );
```

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_feed_content',
    function( string $content ) {
        // Your code here.
        return $content;
    }
);
```

## Parameters

- *`string`* `$content` The event post content.

## Files

- [includes/core/classes/class-feed.php:319](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-feed.php#L319)
```php
apply_filters( 'gatherpress_event_feed_content', $content )
```



[← All Hooks](Hooks)
