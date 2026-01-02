# Event Feed

GatherPress includes enhanced RSS feed functionality for events that provides better organization and more useful information.

## Overview

The feed improvements address several issues with the default WordPress RSS feeds for events:

1. **Event Feeds**: Now only show upcoming events ordered by start date
2. **Enhanced Excerpts**: Include event date, time, and venue information

## Available Feeds

### Enhanced Event Feed
- **URL**: `/events/feed/` (or your custom events slug)
- **Content**: Only upcoming events, ordered by start date and time
- **Enhanced**: Includes event date, time, and venue in excerpts

## Features

### Event Feed Enhancements

The event feed now includes:

- **Filtered Content**: Only shows upcoming events (events that haven't ended)
- **Proper Ordering**: Events are ordered by start date and time (earliest first)
- **Enhanced Excerpts**: Automatically includes event date, time, and venue information
- **Rich Content**: Full event details in the feed content

## Technical Implementation

### Class: `Feed`

The event feed functionality is implemented in the `Feed` class located at:
```
includes/core/classes/class-feed.php
```

### Key Methods

- `filter_events_feed()`: Filters event feeds to show only upcoming events
- `customize_event_excerpt()`: Enhances RSS excerpts with event details
- `customize_event_content()`: Enhances RSS content with event details

### Hooks Used

- `pre_get_posts`: Filters event queries for feeds
- `the_excerpt_rss`: Customizes RSS excerpts
- `the_content_feed`: Customizes RSS content

## Usage Examples

### Subscribe to Event Updates
```xml
<link rel="alternate" type="application/rss+xml" title="Events Feed" href="/events/feed/" />
```

## Customization

### Modifying Feed Content

You can customize the feed content using WordPress filters:

```php
// Customize event excerpt in feeds
add_filter( 'the_excerpt_rss', function( $excerpt ) {
    // Your custom logic here
    return $excerpt;
});

// Customize event content in feeds
add_filter( 'the_content_feed', function( $content ) {
    // Your custom logic here
    return $content;
});
```



## Compatibility

- **WordPress**: Compatible with WordPress 6.7+
- **PHP**: Requires PHP 7.4+
- **Multisite**: Fully compatible with WordPress multisite installations

## Testing

To test the feed improvements:

1. Create some events with different dates
2. Visit the feed URL to verify it works correctly:
   - `/events/feed/`

## Troubleshooting

### Feed Not Working
- Ensure permalinks are enabled
- Flush rewrite rules: Go to Settings > Permalinks and click "Save Changes"
- Check that the `Feed` class is properly instantiated

### Events Not Showing in Feed
- Verify events have valid start/end dates
- Check that events are published
- Ensure events are in the future (for upcoming events feed)

 