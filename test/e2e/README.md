# GatherPress E2E Testing Guide

This directory contains end-to-end tests for GatherPress using Playwright. These tests have been designed with reliability, maintainability, and debugging in mind.

## Running the suite

```bash
npm run test:e2e
```

`pretest:e2e` starts the WordPress instance with `wp-env start --config .wp-env.test.json`, and `playwright.config.js` derives its `baseURL` from the same inputs wp-env reads, in the same order wp-env applies them (`test/e2e/resolve-wp-env-port.js`):

1. `WP_ENV_PORT`, which wp-env consults ahead of every config file. A value that is not a whole number between 1 and 65535 is ignored and resolution falls through to the files.
2. `.wp-env.test.override.json`, which wp-env layers on top of the tracked config.
3. `.wp-env.test.json`, the tracked config.
4. `8888`, wp-env's own default, when none of the above supplies a port.

**Do not hard-code the port anywhere.** The override file is local, untracked, per-checkout state: it is how parallel git worktrees keep off each other's ports, so its value differs between checkouts and is not in git.

This matters more than it looks. If the config and the running environment disagree on the port, Playwright does not fail fast. It finds *a* WordPress on the stale port, either another worktree's or a leftover container, authenticates against it, and then fails deep in the specs with REST errors that read like application bugs.

**A green authentication step is not evidence that you are on the right site.** `global-setup.js` logging `Authentication successful - storage state saved` only proves that *some* WordPress accepted the admin credentials. Every wp-env instance on the machine shares those credentials, so it succeeds just as happily against the wrong one. Screenshots showing a fully rendered admin menu prove the same nothing.

The tell is `rest_no_route` or `rest_type_invalid` on a GatherPress route: that is a WordPress without this plugin active, i.e. the wrong site, not a broken feature. Note also that `wp-env run` / `docker exec` diagnostics resolve the container by *name*, not by the port Playwright is using. It is therefore entirely possible to confirm the plugin is active, the post type exists and the route responds, all against a container the browser never touched. Establish the port first, then diagnose.

To point the suite somewhere else, set `WP_BASE_URL`, which takes precedence over the derived value:

```bash
WP_BASE_URL=http://localhost:8891 npx playwright test
```

To confirm what the config resolved before blaming a spec:

```bash
node -e "console.log(require('./playwright.config.js').use.baseURL)"
```

## Architecture

### Page Object Model (POM)

- **pages/BasePage.js**: Common WordPress admin functionality
- **pages/EventPage.js**: Event-specific page interactions
- **pages/VenuePage.js**: Venue-specific page interactions (future)

### Test Data Management

- **fixtures/TestDataFactory.js**: Consistent test data creation and cleanup
- Automatic cleanup after each test
- Unique identifiers to avoid conflicts

### Test Structure

- **admin-tests/**: Tests for WordPress admin functionality
- **frontend-tests/**: Tests for frontend user interactions
- **api-tests/**: Tests for REST API endpoints (future)

## Best Practices Implemented

### ✅ Reliable Selectors

```javascript
// Good: Semantic, stable selectors
this.selectors = {
    titleInput: '.editor-post-title__input, [aria-label="Add title"]',
    publishButton: '.editor-post-publish-button, button:has-text("Publish")',
};

// Bad: Fragile selectors
'.css-1234567'
'div > span:nth-child(3)'
```

### ✅ Proper Error Handling

```javascript
// Good: Clear error messages with context
if (actualTitle !== title) {
    throw new Error(`Failed to set title. Expected: "${title}", Got: "${actualTitle}"`);
}

// Bad: Generic errors
throw new Error('Something went wrong');
```

### ✅ Robust Waiting Strategies

```javascript
// Good: Wait for specific conditions
await element.waitFor({ state: 'visible', timeout: 10000 });

// Bad: Arbitrary timeouts
await page.waitForTimeout(5000);
```

### ✅ Test Data Isolation

```javascript
// Good: Unique test data
const eventData = testData.createEventData({
    title: 'E2E Test Online Event',  // Will be timestamped
});

// Bad: Static test data
const title = 'Test Event';  // Causes conflicts
```

### ✅ Comprehensive Cleanup

```javascript
test.afterEach(async () => {
    // Cleanup all created test data
    await testData.cleanup();
});
```

## Test Suites

### Admin Tests (`admin-tests/`)

Tests for WordPress admin functionality using Page Object Model.

### Event Display Tests (`event-tests/`)

Basic frontend tests verifying event pages load correctly.

```bash
npm run test:e2e -- event-tests/event-display.spec.js
```

### RSVP Flow Tests (`rsvp-tests/`) - **CURRENTLY SKIPPED**

**Status**: These tests are fully written but currently skipped in CI due to manual setup requirements.

Comprehensive tests for RSVP functionality covering:

- Open RSVP Flow (logged-out users with email)
- Logged-in user RSVP interactions
- Status changes (attending, not attending, waiting list)
- Anonymous checkbox (hide user identity)
- Guest count functionality
- Modal interactions

**11 tests total** - All test logic is complete and ready to run.

#### To Run Locally

These tests require a manually created event with an RSVP block:

```bash
# 1. Create event via WordPress admin at http://localhost:8889/wp-admin
# 2. Add RSVP block to the event
# 3. Set event date to 7+ days in the future
# 4. Publish the event
# 5. Run tests with event URL:
EVENT_URL=http://localhost:8889/event/test-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js
```

#### Future Work Needed

To make these tests production-ready and run in CI, automated event creation is needed. See inline documentation in `rsvp-flows.spec.js` for three potential approaches:

1. **WordPress Playground Blueprint** - WXR import (similar to PR preview workflow)
2. **Playwright Admin UI** - Automate event creation via WordPress editor
3. **Direct Database Seeding** - wp-cli with proper post content

Experimental helper implementations exist in `test/e2e/helpers/` for reference.

## Running Tests

### Local Development

```bash
# Run all E2E tests
npm run test:e2e

# Run specific test file
npm run test:e2e -- test/e2e/admin-tests/gatherpress-event-robust.spec.js

# Run RSVP tests with manual event setup
EVENT_URL=http://localhost:8889/event/your-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js

# Run tests in headed mode (see browser)
npm run test:e2e -- --headed

# Debug mode (pause on failures)
npm run test:e2e -- --debug
```

### With wp-env

```bash
# Start WordPress environment
npm run wp-env start

# Run tests against wp-env
WP_BASE_URL=http://localhost:8889 npm run test:e2e

# Stop environment
npm run wp-env stop
```

### CI/GitHub Actions

Tests run automatically on:

- Push to main/develop branches
- Pull requests affecting E2E code
- Uses single worker to avoid conflicts
- Artifacts saved for debugging failures

## Debugging Test Failures

### 1. View Test Reports

```bash
# Open HTML report
npx playwright show-report

# View specific test traces
npx playwright show-trace test-results/.../trace.zip
```

### 2. Debug Screenshots

Failed tests automatically capture:

- Screenshots on failure
- Video recordings on retry
- Debug screenshots via `takeDebugScreenshot()`

### 3. Verbose Logging

```bash
# Enable debug logging
DEBUG=pw:api npm run test:e2e

# Playwright debug mode
PWDEBUG=1 npm run test:e2e
```

### 4. Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Authentication failures | Storage state corruption | Delete `storageState.json`, restart tests |
| Element not found | Selector changed | Update selector in Page Object |
| Timeout on load | Slow WordPress admin | Increase `navigationTimeout` |
| Test data conflicts | Static test data | Use TestDataFactory for unique data |
| Race conditions | Parallel execution | Disable parallel mode or add proper waits |

## Writing New Tests

### 1. Follow the Pattern

```javascript
const { test, expect } = require('@playwright/test');
const EventPage = require('../pages/EventPage');
const TestDataFactory = require('../fixtures/TestDataFactory');

test.describe('Feature Name', () => {
    let eventPage;
    let testData;

    test.beforeEach(async ({ page }) => {
        eventPage = new EventPage(page);
        testData = new TestDataFactory(page);
        await eventPage.goToAdmin();
    });

    test.afterEach(async () => {
        await testData.cleanup();
    });

    test('should do something specific', async () => {
        // Arrange
        const testData = testData.createEventData({ /* config */ });
        
        // Act
        const result = await eventPage.performAction(testData);
        
        // Assert
        expect(result).toBeTruthy();
    });
});
```

### 2. Add Selectors to Page Objects

Never use raw selectors in tests. Add them to the appropriate Page Object.

### 3. Use Test Steps for Complex Tests

```javascript
test('complex workflow', async () => {
    await test.step('Setup test data', async () => {
        // Setup code
    });
    
    await test.step('Perform main action', async () => {
        // Main test logic
    });
    
    await test.step('Verify results', async () => {
        // Assertions
    });
});
```

### 4. Add Meaningful Test Names

```javascript
// Good: Describes what and why
test('should create online event when venue selector has online option');

// Bad: Vague or technical
test('test event creation');
```

## Migration from Old Tests

### Before (Problematic)

```javascript
test.skip('the user should be able to publish an online event', async ({ page }) => {
    await login({ page, username: 'prashantbellad' });
    await page.getByLabel('Venue Selector').selectOption('33:online-event', { timeout: 60000 });
    // ... fragile code
});
```

### After (Robust)

```javascript
test('should create and publish an online event', async () => {
    const eventData = testData.createEventData({ venueType: 'online' });
    await testData.createOnlineVenue();
    const eventUrl = await eventPage.createEvent(eventData);
    await eventPage.verifyPublishedEvent(eventUrl, eventData.title, 'online');
});
```

## Performance Considerations

- Single worker mode prevents race conditions
- Test data cleanup prevents database bloat
- Selective test running for faster feedback
- Network simulation for testing edge cases

## Future Improvements

- [ ] Add visual regression testing
- [ ] Implement API test coverage
- [ ] Add accessibility testing
- [ ] Create performance benchmarks
- [ ] Add cross-browser testing matrix
