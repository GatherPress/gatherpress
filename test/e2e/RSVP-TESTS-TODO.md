# RSVP E2E Tests - Implementation TODO

## Current Status

✅ **11 comprehensive RSVP tests are fully written and ready**
❌ **Tests are currently skipped in CI** due to manual setup requirements

## What's Complete

### Test Coverage (test/e2e/rsvp-tests/rsvp-flows.spec.js)

All test logic is implemented and covers:

1. **Open RSVP Flow (3 tests)** - Logged-out users requiring email
   - Modal visibility test
   - Email RSVP submission test
   - Email field validation test

2. **Logged-in User RSVP (3 tests)**
   - RSVP without email requirement
   - Status change from attending to not attending
   - Waiting list status handling

3. **Anonymous Checkbox (2 tests)**
   - Checkbox visibility
   - RSVP with anonymous option

4. **Guest Count (1 test)**
   - Adding guests to RSVP

5. **Modal Interactions (2 tests)**
   - Close button functionality
   - Auto-close after successful RSVP

### Test Quality

- Uses proper Playwright selectors and wait strategies
- Follows Page Object Model patterns where appropriate
- Includes proper error messages and assertions
- Clean, maintainable code structure

## What's Missing

**Automated event creation** - Tests currently require manual event setup.

## The Challenge

The tests need a GatherPress event with an RSVP block to run. Currently, this requires:

1. Manual login to WordPress admin (<http://localhost:8889/wp-admin>)
2. Creating an event
3. Adding RSVP block
4. Setting future date
5. Publishing event
6. Passing EVENT_URL to tests

This manual setup prevents CI automation.

## Solution Approaches

Three potential solutions have been explored. Each has blockers that need resolution:

### Option 1: WordPress Playground Blueprint (WXR Import) ⭐ RECOMMENDED

**Approach**: Import demo data via WXR file (similar to PR preview workflow)

**Resources**:

- Demo data URL: <https://raw.githubusercontent.com/GatherPress/gatherpress-demo-data/main/GatherPress-demo-data-0.33.0.xml>
- Contains "Christmas 2025" event with complete RSVP block
- Reference implementation: `.github/scripts/playground-preview/index.js`

**Blocker**: Docker volume mounting in wp-env

The wp-cli importer inside the WordPress container cannot access files from the host filesystem or external URLs directly. The `/tmp` directory doesn't appear to be shared between host and container in wp-env.

**Next Steps**:

1. Research wp-env volume mounting configuration
2. Find shared directory between host and container
3. Download WXR file to shared location
4. Run `wp import` on that file
5. Query for "Christmas 2025" event post ID
6. Use `?p={id}` URL format for tests

**Code Starting Point**: See attempted implementation in rsvp-flows.spec.js git history (around lines 33-85 in earlier commits)

### Option 2: Playwright Admin UI Automation

**Approach**: Use Playwright to create event through WordPress admin interface

**Resources**:

- Reference: `test/e2e/helpers/create-event-via-admin.js`
- Navigate to `/wp-admin/post-new.php?post_type=gatherpress_event`
- Add RSVP block via block inserter
- Publish and extract URL

**Blocker**: "Welcome to the editor" modal

WordPress shows a welcome modal on first editor access that blocks interaction with the title field and other editor elements. Standard dismissal methods (clicking close, pressing Escape) have been unreliable.

**Next Steps**:

1. Research WordPress editor welcome modal dismissal in Playwright
2. Try alternative approaches:
   - Set user meta to skip welcome guide: `wp user meta update 1 show_welcome_guide_for_blocks 0`
   - Use accessibility tree navigation instead of selectors
   - Wait for specific editor ready state
3. Complete the create-event-via-admin.js implementation

**Code Starting Point**: `test/e2e/helpers/create-event-via-admin.js`

### Option 3: Direct Database Seeding (wp-cli)

**Approach**: Create post directly in database with proper RSVP block markup

**Resources**:

- Reference: `test/e2e/helpers/create-event-with-rsvp.php`
- User provided actual RSVP block structure (see git history)
- Uses `wp_insert_post` and `wp_update_post`

**Blocker**: HTTP accessibility

Posts created via wp-cli exist in the database (`wp post exists` confirms) but return 404 when accessed via HTTP. This appears to be a wp-env caching or permalink issue.

**Next Steps**:

1. Try flushing rewrite rules after post creation: `wp rewrite flush`
2. Set specific permalink structure before creating posts
3. Wait for WordPress to rebuild permalinks
4. Try using `?p={id}` format instead of pretty permalinks
5. Verify post status is 'publish' not 'draft'
6. Check if wp-cli is using same database as web server

**Code Starting Point**: `test/e2e/helpers/create-event-with-rsvp.php`

## How to Run Tests Locally (Manual Setup)

For testing the logic without automation:

```bash
# 1. Start wp-env
npm run wp-env start

# 2. Open WordPress admin
# http://localhost:8889/wp-admin
# Username: admin
# Password: password

# 3. Create event manually:
#    - Go to Events → Add New
#    - Add title
#    - Add RSVP block (search for "RSVP" in block inserter)
#    - Set date to 7+ days in future
#    - Publish

# 4. Get event URL from published event

# 5. Run tests
EVENT_URL=http://localhost:8889/event/your-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js
```

## Current Test Results

When running full test suite (`npm run test:e2e`):

```text
✓ 7 tests passing (admin tests + event display)
- 11 tests skipped (RSVP tests)
```

## Expected Outcome

Once automated event creation is implemented:

```text
✓ 18 tests passing
```

All tests should run in CI without manual intervention.

## Files to Review

- **Main test file**: `test/e2e/rsvp-tests/rsvp-flows.spec.js`
- **Helper attempts**: `test/e2e/helpers/create-event-*.js` and `create-event-with-rsvp.php`
- **Blueprint reference**: `.github/scripts/playground-preview/index.js`
- **Demo data**: <https://raw.githubusercontent.com/GatherPress/gatherpress-demo-data/main/GatherPress-demo-data-0.33.0.xml>

## Questions?

For more context on the test architecture and patterns, see `test/e2e/README.md`.
