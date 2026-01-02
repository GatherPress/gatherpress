# Abilities API Integration

**Status:** 🧪 Experimental (Optional Feature)

GatherPress includes optional integration with the [WordPress Abilities API](https://github.com/WordPress/abilities-api), allowing AI assistants and automation tools to discover and interact with GatherPress functionality.

## What is the Abilities API?

The Abilities API is part of WordPress's AI Building Blocks initiative. It provides a standardized way for plugins to declare what they can do in a machine-readable format, enabling AI assistants, automation tools, and other applications to interact with WordPress plugins programmatically.

## Requirements

- **WordPress 6.9+** (Abilities API is bundled in core)
- GatherPress 0.33.0+

## Installation

The Abilities integration is **completely optional**. GatherPress works normally without it.

### To Enable Abilities:

1. **Ensure you're running WordPress 6.9 or later** (the Abilities API is built into WordPress core)
2. GatherPress will automatically detect the Abilities API and register its abilities
3. No additional configuration needed!

**Note:** If you're running WordPress 6.8 or earlier, you can use the [Abilities API plugin](https://github.com/WordPress/abilities-api) as a temporary solution, but upgrading to WordPress 6.9+ is recommended.

## Available Abilities

GatherPress registers eleven abilities:

### 1. `gatherpress/list-venues`
**Permission:** `read` capability  
**Category:** Venue  
**Safe:** Yes (read-only)

Lists all published venues with their details.

**Returns:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "Downtown Library",
      "address": "123 Main St, Springfield",
      "phone": "(555) 123-4567",
      "website": "https://example.com",
      "latitude": "40.7128",
      "longitude": "-74.0060",
      "edit_url": "https://yoursite.com/wp-admin/post.php?post=123&action=edit",
      "permalink": "https://yoursite.com/venue/downtown-library"
    }
  ],
  "message": "Found 3 venue(s)"
}
```

### 2. `gatherpress/list-events`
**Permission:** `read` capability  
**Category:** Event  
**Safe:** Yes (read-only)

Lists all events (both published and draft) with their details. Can optionally search by title or content.

**Parameters:**
- `max_number` (integer, optional): Maximum number of events to return (default: 10, max: 50)
- `search` (string, optional): Search term to find specific events by title or content

**Returns:**
```json
{
  "success": true,
  "data": {
    "events": [
      {
        "id": 456,
        "title": "Book Club Meeting",
        "datetime_start": "2025-01-21 19:00:00",
        "datetime_end": "2025-01-21 21:00:00",
        "venue": "Downtown Library",
        "permalink": "https://yoursite.com/event/book-club-meeting",
        "edit_url": "https://yoursite.com/wp-admin/post.php?post=456&action=edit"
      }
    ],
    "count": 5
  },
  "message": "Found 5 events"
}
```

### 3. `gatherpress/list-topics`
**Permission:** `read` capability  
**Category:** Event  
**Safe:** Yes (read-only)

Lists all available event topics.

**Returns:**
```json
{
  "success": true,
  "data": [
    {
      "id": 12,
      "name": "Book Club",
      "slug": "book-club",
      "description": "Monthly book discussions",
      "parent": 0
    }
  ],
  "message": "Found 5 topics"
}
```

### 4. `gatherpress/calculate-dates`
**Permission:** `read` capability  
**Category:** Event  
**Safe:** Yes (read-only)

Calculates recurring dates based on patterns like "3rd Tuesday" or "every Monday". Use this BEFORE creating recurring events to get accurate dates.

**Parameters:**
- `pattern` (string, required): The recurrence pattern. Examples: "3rd Tuesday", "every Monday", "first Friday", "last Wednesday"
- `occurrences` (integer, required): Number of occurrences to calculate
- `start_date` (string, optional): Starting date in Y-m-d format. Defaults to today if not provided.

**Returns:**
```json
{
  "success": true,
  "data": {
    "dates": [
      "2025-01-21",
      "2025-02-18",
      "2025-03-18",
      "2025-04-15",
      "2025-05-20",
      "2025-06-17"
    ],
    "pattern": "3rd Tuesday",
    "count": 6
  },
  "message": "Calculated 6 dates."
}
```

### 5. `gatherpress/create-venue`
**Permission:** `edit_posts` capability  
**Category:** Venue  
**Safe:** No (creates content)

Creates a new venue.

**Parameters:**
- `name` (string, required): Name of the venue
- `address` (string, required): Full address of the venue
- `phone` (string, optional): Phone number
- `website` (string, optional): Website URL

**Returns:**
```json
{
  "success": true,
  "venue_id": 789,
  "edit_url": "https://yoursite.com/wp-admin/post.php?post=789&action=edit",
  "message": "Venue \"Downtown Library\" created successfully."
}
```

### 6. `gatherpress/create-topic`
**Permission:** `manage_categories` capability  
**Category:** Event  
**Safe:** No (creates content)

Creates a new event topic for categorizing events.

**Parameters:**
- `name` (string, required): Name of the topic
- `description` (string, optional): Description of the topic
- `parent_id` (integer, optional): Parent topic ID for hierarchical topics

**Returns:**
```json
{
  "success": true,
  "topic_id": 15,
  "name": "Book Club",
  "edit_url": "https://yoursite.com/wp-admin/term.php?taxonomy=gatherpress_topic&tag_ID=15",
  "message": "Topic \"Book Club\" created successfully."
}
```

### 7. `gatherpress/create-event`
**Permission:** `edit_posts` capability  
**Category:** Event  
**Safe:** No (creates content)

Creates a new event. **Events are created as drafts by default** for safety.

**Parameters:**
- `title` (string, required): Event title
- `datetime_start` (string, required): Start date/time in `Y-m-d H:i:s` format (e.g., `2025-01-21 19:00:00`)
- `datetime_end` (string, optional): End date/time in `Y-m-d H:i:s` format (defaults to 2 hours after start)
- `venue_id` (integer, optional): ID of the venue for this event
- `description` (string, optional): Event description/content
- `post_status` (string, optional): Either `draft` or `publish` (default: `draft`)
- `topic_ids` (array, optional): Array of topic IDs to assign to this event

**Returns:**
```json
{
  "success": true,
  "event_id": 999,
  "post_status": "draft",
  "edit_url": "https://yoursite.com/wp-admin/post.php?post=999&action=edit",
  "message": "Event \"Book Club Meeting\" created as draft."
}
```

### 8. `gatherpress/update-venue`
**Permission:** `edit_posts` capability  
**Category:** Venue  
**Safe:** No (modifies content)

Updates an existing venue's information.

**Parameters:**
- `venue_id` (integer, required): ID of the venue to update
- `name` (string, optional): New name for the venue
- `address` (string, optional): New address
- `phone` (string, optional): New phone number
- `website` (string, optional): New website URL

**Example:**
```json
{
  "venue_id": 123,
  "phone": "(555) 987-6543",
  "website": "https://newsite.com"
}
```

**Returns:**
```json
{
  "success": true,
  "venue_id": 123,
  "edit_url": "https://yoursite.com/wp-admin/post.php?post=123&action=edit",
  "message": "Venue \"Downtown Library\" updated successfully."
}
```

### 9. `gatherpress/update-event`
**Permission:** `edit_posts` capability  
**Category:** Event  
**Safe:** No (modifies content)

Updates an existing event's information. Can update any combination of fields.

**Parameters:**
- `event_id` (integer, required): ID of the event to update
- `title` (string, optional): New event title
- `datetime_start` (string, optional): New start date/time in `Y-m-d H:i:s` format
- `datetime_end` (string, optional): New end date/time in `Y-m-d H:i:s` format
- `venue_id` (integer, optional): New venue ID
- `description` (string, optional): New event description
- `post_status` (string, optional): New status (`draft` or `publish`)
- `topic_ids` (array, optional): Array of topic IDs to assign to this event

**Example - Change event time:**
```json
{
  "event_id": 999,
  "datetime_start": "2025-01-21 20:00:00",
  "datetime_end": "2025-01-21 22:00:00"
}
```

**Example - Change venue:**
```json
{
  "event_id": 999,
  "venue_id": 456
}
```

**Returns:**
```json
{
  "success": true,
  "event_id": 999,
  "post_status": "draft",
  "edit_url": "https://yoursite.com/wp-admin/post.php?post=999&action=edit",
  "message": "Event \"Book Club Meeting\" updated successfully."
}
```

### 10. `gatherpress/search-events`
**Permission:** `read` capability  
**Category:** Event  
**Safe:** Yes (read-only)

Search for events by title or content. Returns both published and draft events.

**Parameters:**
- `search_term` (string, required): Search term to find events by title or content
- `max_number` (integer, optional): Maximum number of events to return (default: 10)

**Returns:**
```json
{
  "success": true,
  "data": {
    "events": [
      {
        "id": 456,
        "title": "Book Club Meeting",
        "status": "publish",
        "datetime_start": "2025-01-21 19:00:00",
        "datetime_end": "2025-01-21 21:00:00",
        "timezone": "America/New_York",
        "venue_id": 123,
        "edit_url": "https://yoursite.com/wp-admin/post.php?post=456&action=edit"
      }
    ],
    "count": 1
  },
  "message": "Found 1 event matching \"Book Club\"."
}
```

### 11. `gatherpress/update-events-batch`
**Permission:** `edit_posts` capability  
**Category:** Event  
**Safe:** No (modifies content)

Update multiple events at once based on search criteria. Perfect for bulk operations like "change all Book Club events to 8pm" or "move all events from Old Library to New Library".

**IMPORTANT:** When changing event times, use this ability. For example, "change events from 7pm to 8pm" means set the start time to 8pm (not search for events at 7pm).

**Parameters:**
- `search_term` (string, required): Search term to find events to update (searches title and content)
- `datetime_start` (string, optional): New start datetime in Y-m-d H:i:s format
- `datetime_end` (string, optional): New end datetime in Y-m-d H:i:s format
- `venue_id` (integer, optional): New venue ID to assign to all matching events

**Example - Change all Book Club events to 8pm-10pm:**
```json
{
  "search_term": "Book Club",
  "datetime_start": "2025-01-21 20:00:00",
  "datetime_end": "2025-01-21 22:00:00"
}
```

**Example - Move all events from one venue to another:**
```json
{
  "search_term": "Old Library",
  "venue_id": 456
}
```

**Returns:**
```json
{
  "success": true,
  "data": {
    "updated_count": 5,
    "errors": []
  },
  "message": "Updated 5 events matching \"Book Club\"."
}
```

## GatherPress AI Assistant Plugin

For the easiest way to use the Abilities API, install the [GatherPress AI Assistant plugin](https://github.com/jmarx/gatherpress-ai-assistant).

This companion plugin adds a WordPress admin interface where you can:
- Manage events with natural language prompts
- Create recurring events with simple descriptions
- Bulk update multiple events at once
- No command-line or external tools needed

**Quick Start:**
1. Install GatherPress on WordPress 6.9+
2. Install the [GatherPress AI Assistant plugin](https://github.com/jmarx/gatherpress-ai-assistant)
3. Go to Events → AI Assistant in WordPress admin
4. Enter your OpenAI API key
5. Start creating events with prompts like:
   - "Create book club events on 3rd Tuesday for 6 months at Downtown Library"
   - "Change all happy hour events from 7pm to 8pm"
   - "Move all events to a different venue"

## Testing the Integration

### Recommended: GatherPress AI Assistant

Use the [GatherPress AI Assistant plugin](https://github.com/jmarx/gatherpress-ai-assistant) for a WordPress admin interface with natural language prompts.

### For Developers: REST API Testing

Test abilities directly via REST API:

```bash
# List venues
curl -X POST https://yoursite.local/wp-json/abilities-api/v1/execute/gatherpress--list-venues \
  -u "username:application-password"

# Create event
curl -X POST https://yoursite.local/wp-json/abilities-api/v1/execute/gatherpress--create-event \
  -u "username:application-password" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Book Club - January 2025",
    "datetime_start": "2025-01-21 19:00:00",
    "venue_id": 123,
    "post_status": "draft"
  }'
```

**Note:** Create an Application Password at `Users > Your Profile > Application Passwords`

## Use Cases

### Recurring Events
While GatherPress doesn't have built-in recurring events, AI can calculate dates server-side and create multiple events:

**Prompt:** "Create a weekly coffee meetup every Tuesday at 9am for the next 8 weeks at the Community Center"

**What happens:**
1. AI calls `gatherpress/calculate-dates` with pattern "every Tuesday" and occurrences 8
2. Server returns 8 accurate dates
3. AI calls `gatherpress/create-event` 8 times with the calculated dates
4. All events created as drafts
5. You review and publish

**Why this is better:** Date calculations happen server-side, eliminating AI math errors.

### Bulk Event Creation
**Prompt:** "Create events for a 5-day conference from May 1-5, 2025 at the Convention Center. Day 1: Registration (9am-5pm), Day 2: Workshops (9am-6pm), Day 3: Keynotes (9am-5pm), Day 4: Panels (10am-4pm), Day 5: Closing Ceremony (10am-2pm)"

AI creates all 5 events with appropriate times.

### Venue Discovery
**Prompt:** "What venues do we have available in downtown?"

AI lists all venues, you can ask follow-up questions about capacity, address, etc.

### Topic Management
**Prompt:** "Create a new topic called 'Workshops' and tag all events with 'training' or 'workshop' in the title with it"

**What happens:**
1. AI calls `gatherpress/create-topic` to create "Workshops" topic
2. AI searches for events with "training" or "workshop"
3. AI updates each event to add the new topic ID

### Complex Recurring Patterns
**Prompt:** "Create a monthly board meeting on the 3rd Tuesday at 6pm for the next year"

**What happens:**
1. AI calls `gatherpress/calculate-dates` with pattern "3rd Tuesday" and occurrences 12
2. Server calculates all 12 dates (e.g., Jan 21, Feb 18, Mar 18, etc.)
3. AI creates 12 events with the exact dates
4. No AI math errors - dates are calculated server-side!

### Bulk Event Updates
**Prompt:** "Change all Book Club events from 7pm to 8pm"

**What happens:**
1. AI calls `gatherpress/update-events-batch` with search_term "Book Club" and new start time
2. Server finds all matching events and updates them in one operation
3. All Book Club events updated to 8pm start time

**Prompt:** "Move all events at the Old Library to the New Library"

**What happens:**
1. AI calls `gatherpress/update-events-batch` with search_term "Old Library" and new venue_id
2. Server finds all matching events and updates their venue
3. All events moved to New Library

**Why this is better:** Batch operations are faster and more reliable than individual updates.

## Safety Features

### Draft by Default
All events created via the ability default to `draft` status. This gives you a chance to:
- ✅ Review AI-generated content
- ✅ Fix any mistakes
- ✅ Delete unwanted events
- ✅ Publish when ready

### Permission Checks
- **Read abilities** require `read` capability (all logged-in users)
- **Write abilities** require `edit_posts` capability (editors and above)

### Error Handling
All abilities return structured responses with:
- `success`: Boolean indicating success/failure
- `message`: Human-readable description
- `data` or error details

### ⚠️ Important Warning

**AI can make mistakes.** While the Abilities API provides powerful automation:
- ✅ **Always review** AI-generated or AI-modified content before publishing
- ✅ **Test with drafts first** - don't give AI permission to publish directly until you're confident
- ✅ **Start small** - test with a few events before doing bulk operations
- ⚠️ **Update abilities can modify existing published events** - AI could change live event details
- ⚠️ **No delete abilities** - we intentionally don't expose delete operations to prevent accidental data loss

**Best Practice:** Use draft mode for everything, review manually, then publish. Don't automate publishing until you're very confident in the AI's accuracy.

## Architecture

### Code Location
- **Integration class:** `includes/core/classes/class-abilities-integration.php`
- **Initialization:** `includes/core/classes/class-setup.php`
- **Documentation:** `docs/developer/abilities-api.md`

### How It Works
1. `Abilities_Integration` checks if `wp_register_ability()` function exists
2. If not found, integration silently does nothing (zero impact)
3. If found, registers abilities on the `init` hook
4. Each ability has an execute callback that wraps existing GatherPress functionality

### Extending
To add more abilities, add methods to the `Abilities_Integration` class:

```php
protected function register_my_ability(): void {
    wp_register_ability(
        'gatherpress/my-ability',
        array(
            'label'               => __( 'My Ability', 'gatherpress' ),
            'description'         => __( 'Description here', 'gatherpress' ),
            'execute_callback'    => array( $this, 'execute_my_ability' ),
            'permission_callback' => static function (): bool {
                return current_user_can( 'some_capability' );
            },
            'parameters'          => array(
                'param_name' => array(
                    'type'        => 'string',
                    'description' => __( 'Parameter description', 'gatherpress' ),
                    'required'    => true,
                ),
            ),
            'meta'                => array(
                'show_in_rest' => true,
            ),
        )
    );
}

public function execute_my_ability( array $params ): array {
    // Your logic here
    return array(
        'success' => true,
        'data'    => $result,
        'message' => 'Success message',
    );
}
```

Then call `$this->register_my_ability()` in the `register_abilities()` method.

## Troubleshooting

### Abilities Not Showing Up

**Check WordPress version:**
```bash
wp core version
```
Should be 6.9 or later.

**Check for PHP errors:**
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

**Verify registration:**
```bash
curl https://yoursite.local/wp-json/abilities-api/v1/abilities
```

Should show `gatherpress/list-venues`, `gatherpress/list-events`, etc.

### Authentication Issues

**Make sure you've created an Application Password:**
1. Go to Users > Your Profile
2. Scroll to "Application Passwords"
3. Enter a name (e.g., "Testing")
4. Click "Add New Application Password"
5. Copy the generated password (it won't be shown again)

**Test authentication:**
```bash
curl -u "username:application-password" \
  https://yoursite.local/wp-json/wp/v2/users/me
```

### Events Created but Dates Wrong

Make sure your datetime format is correct: `Y-m-d H:i:s`

**Good:** `2025-01-21 19:00:00`  
**Bad:** `Jan 21, 2025 7pm`, `2025/01/21 19:00:00`

## Learn More

- [Abilities API Documentation](https://github.com/WordPress/abilities-api)
- [MCP Adapter](https://github.com/WordPress/mcp-adapter)
- [WordPress AI Initiative](https://make.wordpress.org/ai/)
- [WordPress 6.9 AI Features](https://make.wordpress.org/core/roadmap-to-6-9/)

## Feedback

This is an experimental feature. We'd love to hear about your experience:

- [GatherPress GitHub Issues](https://github.com/GatherPress/gatherpress/issues)
- [WordPress #core-ai Slack](https://wordpress.slack.com/archives/C08TJ8BPULS)

## Future Plans

As the WordPress AI ecosystem matures, we plan to:
- ✅ ~~Add event and venue update abilities~~ (Done!)
- ✅ ~~Add bulk operations~~ (Done!)
- ✅ ~~Add search/filter capabilities~~ (Done!)
- ✅ ~~Add topic management~~ (Done!)
- ✅ ~~Add server-side date calculations~~ (Done!)
- Add RSVP management abilities
- Integrate with WordPress Command Palette
- Support more AI assistants and tools
- Add ability categories and better discoverability
- Consider carefully scoped delete operations with additional safeguards

Stay tuned! 🚀

