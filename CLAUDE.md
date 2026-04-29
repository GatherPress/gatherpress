# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Language

Always use **US English** spelling in all code, comments, and documentation. When in doubt, use the spelling WordPress core uses.

## Development Commands

### PHP Development

- `npm run lint:php` - Run PHP CodeSniffer linting
- `npm run lint:php:fix` - Auto-fix PHP coding standards issues
- `npm run lint:phpstan` - Run PHPStan static analysis

### JavaScript Development

- `npm run build` - Build production assets
- `npm run start` - Start development server with hot reload
- `npm run lint:js` - Lint JavaScript files
- `npm run lint:js:fix` - Fix JavaScript linting issues
- `npm run lint:css` - Lint CSS/SCSS files
- `npm run lint:css:fix` - Fix CSS linting issues
- `npm run test:unit:js` - Run JavaScript unit tests with coverage

### Testing

- `npm run test:e2e` - Run Playwright end-to-end tests
- `npm run test:unit:php` - Run PHP unit tests with coverage (requires wp-env)
- `npm run test:unit:js` - Run JavaScript unit tests with coverage

### WordPress Environment

- `npm run wp-env` - Manage local WordPress environment
- `npm run playground` - Start WordPress Playground server
- `npm run playground:mount` - Start WordPress Playground with plugin mounted
- `npm run plugin-zip` - Create distributable plugin zip

### Documentation & Linting

- `npm run lint:md:docs` - Lint markdown documentation files
- `npm run lint:md:js` - Lint markdown in JavaScript files
- `npm run lint:pkg-json` - Lint package.json format
- `npm run format` - Format code using wp-scripts formatter

### Utilities

- `npm run check-engines` - Check Node.js and npm version compatibility
- `npm run check-licenses` - Check package licenses
- `npm run packages-update` - Update WordPress packages

## Architecture Overview

GatherPress is a WordPress event management plugin with a block-based architecture:

### Core PHP Structure

- **Namespace**: `GatherPress\Core`
- **Main classes** in `includes/core/classes/`:
    - `Event` - Core event management and data handling
    - `Rsvp` - RSVP functionality and attendee management
    - `Venue` - Location and venue management
    - `Block` - Base class for Gutenberg blocks
    - `Assets` - Asset loading and management
    - `Settings` - Plugin configuration management

### Block Architecture

- **Block definitions** in `src/blocks/[block-name]/`:
    - `block.json` - Block registration and metadata
    - `edit.js` - Block editor interface
    - `render.php` - Server-side rendering (for dynamic blocks)
    - `style.scss` - Block styling
    - `view.js` - Frontend interactivity

### Key Blocks

- `rsvp` - Event RSVP management with templating system
- `event-date` - Date and time display
- `online-event` - Online event link management
- `venue` - Location and map integration
- `add-to-calendar` - Calendar integration

### Frontend Architecture

- Uses WordPress Block Editor (Gutenberg) patterns
- React components in `src/components/`
- Shared helpers in `src/helpers/`
- State management via WordPress data stores in `src/stores/`

### Template System

The RSVP block uses a sophisticated template system (`src/blocks/rsvp/templates/`) with different states:

- `attending.js`
- `not-attending.js` 
- `waiting-list.js`
- `past.js`
- `no-status.js`

### Post Type Supports (Extensibility)

GatherPress uses custom `post_type_supports` to decouple features from specific post types. This allows developers to enable GatherPress features on their own custom post types.

**Event post type supports** (declared on post types that act as events):

- `gatherpress-event-date` — **Core event identifier.** Datetime storage, the `gatherpress_events` DB table, date-based queries, timezone handling, and related blocks (event-date, add-to-calendar)
- `gatherpress-rsvp` — Comment-based RSVP system, attendee management, waiting list, RSVP blocks (rsvp, rsvp-form, rsvp-response, rsvp-template)
- `gatherpress-venue` — Association with a venue post type via the `_gatherpress_venue` taxonomy, venue selector in the editor, and venue block rendering
- `gatherpress-online-event` — Online event link meta (stored on the event), online-event term in the taxonomy, and online-event block rendering

**Venue post type supports** (declared on post types that act as venues):

- `gatherpress-venue-information` — **Core venue identifier.** Registers five individual editor-writable post meta keys (`gatherpress_address`, `gatherpress_latitude`, `gatherpress_longitude`, `gatherpress_phone`, `gatherpress_website`), all `show_in_rest`, so venue fields can be bound to blocks via `core/post-meta` block bindings. Also wires up the venue detail blocks and automatic `_gatherpress_venue` taxonomy term management. Meta revisions (`revisions_enabled`) are opted-in per post type and only applied when the post type declares `revisions` in its `supports` array. Declaring this support is what makes a post type a venue source.
- `gatherpress-venue-map` — Map display meta (show/zoom/height) and the venue map block

**How it works:**

- `gatherpress_event` registers all event supports during `register_post_type()`
- `gatherpress_venue` registers all venue supports during `register_post_type()`
- PHP checks use `post_type_supports( $post_type, 'gatherpress-event-date' )` instead of `Event::POST_TYPE === $post_type`
- PHP checks use `post_type_supports( $post_type, 'gatherpress-venue-information' )` instead of `Venue::POST_TYPE === $post_type`
- Queries use `get_post_types_by_support( 'gatherpress-event-date' )` or `get_post_types_by_support( 'gatherpress-venue-information' )` instead of hardcoded post type slugs
- JS checks use `select('core').getPostType(slug)?.supports?.['gatherpress-event-date']` via the WordPress data store
- JS venue post type resolution uses `select('core/editor').getEditorSettings()?.gatherpress?.config?.venuePostTypes` (exposed via `block_editor_settings_all` filter)
- Post-type-specific hooks are registered inside `register_post_meta()` or similar `init` callbacks that loop over supported post types at priority 11

**Developer usage:**

```php
// Enable event dates on a custom event post type.
add_post_type_support( 'my_custom_event', 'gatherpress-event-date' );

// Create a custom venue post type.
register_post_type( 'my_custom_venue', array(
    'supports' => array( 'title', 'editor', 'gatherpress-venue-information', 'gatherpress-venue-map' ),
) );

// Map a custom event post type to a custom venue post type.
add_filter( 'gatherpress_venue_post_type', function( $post_type, $event_post_type ) {
    if ( 'my_custom_event' === $event_post_type ) {
        return 'my_custom_venue';
    }
    return $post_type;
}, 10, 2 );
```

**When adding new post_type_supports:**

1. Use kebab-case with `gatherpress-` prefix (e.g., `gatherpress-venue-map`)
2. Add the support to the `supports` array in the relevant `register_post_type()` call
3. Replace `Event::POST_TYPE === get_post_type()` checks with `post_type_supports( ..., 'gatherpress-event-date' )`
4. Replace `Venue::POST_TYPE === get_post_type()` checks with `post_type_supports( ..., 'gatherpress-venue-information' )`
5. Replace `'post_type' => Event::POST_TYPE` in queries with `get_post_types_by_support( 'gatherpress-event-date' )`
6. Register post-type-specific hooks inside `register_post_meta()` or similar `init` callbacks at priority 11 that loop over supported post types
7. Update JS helpers to check supports via `select('core').getPostType(slug)?.supports`
8. Update corresponding unit tests (PHP and JS mocks)

### Database Schema

- Custom post types: `gatherpress_event`, `gatherpress_venue`
- Custom taxonomy: `_gatherpress_rsvp_status`
- Uses WordPress comments system for RSVP storage
- Venue data stored as post meta

### Testing Structure

- **PHP tests**: `test/unit/php/` using PHPUnit
- **JavaScript tests**: `test/unit/js/` using Jest
- **E2E tests**: `test/e2e/` using Playwright
- Test configuration: `phpunit.xml.dist`, `jest.config.js`, `playwright.config.js`

### Dependencies

- **PHP**: Requires WordPress core, uses PMC Unit Test framework
- **JavaScript**: WordPress block editor packages, React components
- **External**: Leaflet for maps, React-Modal (being phased out)

### Development Workflow

- Uses `wp-env` for local WordPress development
- Webpack build system via `@wordpress/scripts`
- PHP CodeSniffer with WordPress coding standards
- PHPStan for static analysis
- SonarCloud integration for code quality

## Coding Guidelines

When working with this codebase:

1. Always run linting before committing
2. Use existing WordPress hooks and filters patterns
3. Follow WordPress coding standards
4. Test both PHP and JavaScript components
5. Consider block editor compatibility when making changes

### Auto-generated developer hook docs

`docs/developer/hooks/` is regenerated automatically by CI — the `extract-wp-hooks-as-docs.yml` workflow runs on every push to `develop` that touches a `.php` file, runs `vendor/bin/extract-wp-hooks.php`, and opens a dedicated `fix/extract-wp-hooks-{sha}` PR titled "Hook docs updated!" with the regen.

- **Do not commit changes under `docs/developer/hooks/` in feature PRs.** Add the new filter/action with a correct `@since` / `@param` / description docblock, and let CI regenerate the markdown on merge. Committing regen in a feature PR just creates duplicate diffs and churn when the auto-PR lands.
- If you regenerated locally to sanity-check a docblock, revert before committing: `git checkout -- docs/developer/hooks/` and `rm` any newly-created hook markdown files.
- The exception is a `fix/extract-wp-hooks-{sha}` branch itself — regen is the point of that branch. Merge-conflict resolution there is also fair game (take develop's version for any conflict, then re-run `vendor/bin/extract-wp-hooks.php` against the merged state).
- This applies only to `docs/developer/hooks/`. `docs/user/` and the rest of `docs/developer/` are hand-maintained.

### JavaScript/TypeScript Guidelines

- **No console.log statements**: JavaScript linting will fail if `console.log()` statements are present in the code. For debugging purposes, use proper debugging tools or remove all console statements before committing.
- **E2E Tests**: E2E tests should focus on testing GatherPress functionality rather than WordPress UI implementation details. Tests should be resilient to development environment JavaScript errors.

### PHP Coding Standards

- **Use statements**: Always use `use` statements at the top of files for classes and functions instead of fully qualified namespace calls
    - ✅ Good: `use GatherPress\Core\Event\Event;` then `new Event( $post_id )`
    - ❌ Bad: `new \GatherPress\Core\Event( $post_id )`
    - For functions: `use function GatherPress\Core\filter_input;` then `filter_input( $value )`
- **Namespace resolution**: When moving code between namespaces, ensure proper imports are updated
- **Method organization**: Place related methods in logically grouped classes (e.g., form-related methods in `Rsvp_Form`)
- **Singleton pattern**: Many GatherPress classes use the Singleton trait - check if a class has `use Singleton;`
    - **Singleton classes** (Blocks, Settings, Setup classes): Use `ClassName::get_instance()`
        - ✅ Good: `$instance = Blocks\Rsvp::get_instance(); $instance->method();`
        - ❌ Bad: `new Blocks\Rsvp()` (will fail - constructor is protected)
    - **Regular classes** (Event, Rsvp, Venue): Use normal instantiation with parameters
        - ✅ Good: `$event = new Event( $post_id ); $event->method();`
        - ❌ Bad: `Event::get_instance()` (doesn't exist for these classes)
    - In tests, always check the class structure before deciding instantiation method
    - Look for `use Singleton;` trait to determine if `::get_instance()` should be used

### PHP Linting Requirements

Based on WordPress Coding Standards (WPCS), always ensure:

- **Inline comments**: All inline comments must end with proper punctuation (periods)
    - ✅ Good: `// Process the data and return results.`
    - ❌ Bad: `// Process the data and return results`
- **PHPDoc blocks**: Multi-line variable declarations require proper PHPDoc format with short descriptions
    - ✅ Good:

        ```php
        /**
         * WordPress comment insertion result.
         *
         * @var int|false|\WP_Error $result WordPress may return WP_Error via filters.
         */
        ```

    - ❌ Bad: `/** @var int|false|\WP_Error $result - WordPress may return WP_Error via filters. */`
- **PHPDoc short description must start with a capital letter** (`Generic.Commenting.DocComment.ShortNotCapital`). This trips most often when a test/method docblock starts with the bare method name being described — since method names are lowercase, the line starts lowercase and lint fails. Reword as a proper sentence.
    - ✅ Good: `Returns '' from get_taxonomy when wrapping a non-venue post.`
    - ❌ Bad: `get_taxonomy returns '' when wrapping a non-venue post.`
- **Type handling**: WordPress functions may return multiple types; handle all cases with proper type checking
    - Use `is_wp_error()`, `is_numeric()`, and similar WordPress/PHP functions
    - Cast types explicitly when needed: `(int) $comment->comment_post_ID`

### Test Coverage

**Full coverage is the bar — partial branch coverage does not count.** When you add or touch code, cover every branch: each side of a ternary, each arm of `||` / `??` fallbacks, each `if` / `else`, each optional-chain short-circuit, each falsy-default spread. If a line like `error?.message || __( 'fallback' )` has a test for the truthy side but not the falsy side, the PR is incomplete — add the missing case.

- ✅ Good: a test for `error?.message` present *and* a test where `.message` is absent so the `__( 'fallback' )` branch executes.
- ✅ Good: a test for `current.meta` being populated *and* a test where it's undefined so `( current.meta || {} )` spreads the empty default.
- ✅ Good: a test where `response.descriptors` arrives *and* a test where it's missing so `response?.descriptors || {}` falls through.
- ❌ Bad: shipping with the coverage tool reporting "1/2" on a branch and calling it done.

Coverage gaps flagged by SonarCloud or the `test:unit:js` / `test:unit:php` coverage reports must be closed before merging — add the tests, don't suppress. `@codeCoverageIgnore` is reserved for genuinely unreachable/untestable code (the existing uses on missing-GD branches, unwritable filesystem branches) and is not a shortcut for "I didn't write the test yet."

**Multisite test group**: GatherPress runs two separate PHPUnit test suites in CI — a standard single-site run and a multisite run. Tests that require a multisite WordPress environment are annotated with `@group multisite`. The standard `phpunit.xml.dist` excludes this group so it does not run locally via `npm run test:unit:php`, but CI runs it separately and merges the coverage data before the PR coverage check.

- **Never remove `@group multisite`** from test classes that carry it — those tests exist and run in CI.
- **Never add `@codeCoverageIgnore`** to multisite-only code paths (e.g., `switch_to_blog`, `get_sites`, `is_plugin_active_for_network` branches) — those lines are covered by the multisite test run.
- If the PR coverage check shows 0% for a class whose tests are in `@group multisite`, the most likely cause is that the multisite test suite is **failing** (e.g., a `test_setup_hooks` assertion no longer matches after a hook was changed). Fix the failing test rather than suppressing coverage.

When writing PHPUnit tests that need WordPress post context:

- **Global variable override**: Never directly assign to `$GLOBALS['post']` - WordPress Coding Standards prohibit this
    - ❌ Bad: `$GLOBALS['post'] = get_post( $post_id );`
    - ✅ Good: `$this->go_to( get_permalink( $post_id ) );` (sets up proper WordPress query context)
- **Post context setup**: Use `$this->go_to()` method to set up WordPress global query and post context
    - This properly initializes `get_the_ID()`, `get_queried_object()`, and other WordPress globals
    - Example: `$this->go_to( get_permalink( $post_id ) );` before calling methods that use `get_the_ID()`
- **Meta data setup**: Use `add_post_meta()` instead of factory meta parameter for better test clarity
    - ✅ Good: `add_post_meta( $post_id, 'meta_key', 'value' );`
    - Works better with WordPress testing framework than factory meta arrays

### JavaScript Coding Standards

When working with JavaScript code:

- **Inline comments**: All inline comments must end with proper punctuation (periods)
    - ✅ Good: `// Check if this is a form-field block with guest count field name.`
    - ❌ Bad: `// Check if this is a form-field block with guest count field name`
- **Comment consistency**: Apply the same punctuation standards across PHP and JavaScript for consistency
- **Block comments**: Multi-line JSDoc comments should follow proper formatting with periods in descriptions

## Known Issues / Technical Debt

### @wordpress/env Version Pinned

**Issue**: `@wordpress/env` is currently pinned to version `10.14.0` in `package.json` due to a Docker build bug in versions 10.15.0+.

**Problem**: Versions 10.15.0 and later fail during Docker container build with this error:

```bash
RUN composer global require --dev phpunit/phpunit:"^5.7.21 || ^6.0 || ^7.0 || ^8.0 || ^9.0 || ^10.0"
target cli: failed to solve: process did not complete successfully: exit code: 1
```

**Action Required**:

- Monitor the [@wordpress/env releases](https://www.npmjs.com/package/@wordpress/env) for a fix
- Test upgrading to the latest version periodically by:
  1. Changing `"@wordpress/env": "10.14.0"` to `"@wordpress/env": "^10.37.0"` (or latest)
  2. Running `npm install`
  3. Running `npm run test:unit:php` locally to verify Docker build succeeds
  4. If successful, keep the upgrade; if not, revert and wait for the next release
- Related GitHub Actions workflows that depend on this: `phpunit-tests.yml`, `sonarcloud.yml`, `pr-coverage.yml`, `e2e-tests.yml`

**Tracking**: This issue was identified on December 23, 2025 while fixing CI test failures.

## Planned Improvements for v0.34.0

### JavaScript Test Coverage Improvements

**Goal**: Increase unit test coverage by extracting business logic from UI components into testable helper functions.

#### High Priority - Extract & Test

1. **Extract DOM utilities from `src/helpers/interactivity.js`**

   Create new file: `src/helpers/dom-utils.js`

   Extract the following for better testability:

   ```javascript
   /**
    * Checks if a DOM element is visible.
    *
    * @param {HTMLElement} element - The element to check.
    * @return {boolean} True if the element is visible, false otherwise.
    */
   export function isElementVisible(element) {
       return (
           null !== element.offsetParent &&
           'hidden' !== window.getComputedStyle(element).visibility &&
           '0' !== window.getComputedStyle(element).opacity
       );
   }

   /**
    * Filters an array of elements to only visible ones.
    *
    * @param {HTMLElement[]} elements - Array of elements to filter.
    * @return {HTMLElement[]} Array of visible elements.
    */
   export function getVisibleElements(elements) {
       return Array.from(elements).filter(isElementVisible);
   }
   ```

   Then update `manageFocusTrap()` in `interactivity.js` to use the extracted function.

2. **Extract RSVP utilities from `src/blocks/rsvp-response/rsvp-manager.js`**

   Create new file: `src/helpers/rsvp-utils.js`

   Extract the following business logic:

   ```javascript
   /**
    * Maps user list to username-keyed object for suggestions.
    *
    * @param {Object[]} userList - Array of user objects.
    * @return {Object} Object mapping usernames to user objects.
    */
   export function mapUsersToSuggestions(userList) {
       return userList?.reduce(
           (accumulator, user) => ({
               ...accumulator,
               [user.username]: user,
           }),
           {},
       ) ?? {};
   }

   /**
    * Gets attendees that have been removed from the list.
    *
    * @param {Object[]} currentAttendees - Current list of attendees.
    * @param {Object[]} newTokens        - New token list.
    * @return {Object[]} Array of removed attendees.
    */
   export function getRemovedAttendees(currentAttendees, newTokens) {
       return currentAttendees.filter(
           (attendee) => !newTokens.some((item) => item.id === attendee.userId)
       );
   }

   /**
    * Gets users that have been added to the list.
    *
    * @param {string[]} tokens          - Array of username tokens.
    * @param {Object}   userSuggestions - Mapping of usernames to user objects.
    * @return {Object[]} Array of added user objects.
    */
   export function getAddedUsers(tokens, userSuggestions) {
       return tokens
           .filter((token) => userSuggestions[token])
           .map((token) => userSuggestions[token]);
   }
   ```

#### Medium Priority

1. **Add unit tests for existing `interactivity.js` functions**

   Create: `test/unit/js/src/helpers/interactivity.test.js`

   Test these already well-structured functions:

   - `initPostContext()` - Pure state transformation
   - `getNonce()` - Async with caching (test with mocks)
   - `sendRsvpApiRequest()` - Complex API logic
   - `manageFocusTrap()` - Focus management
   - `setupCloseHandlers()` - Event handler setup

2. **Extract validation logic from components**

   Components with extractable business logic:

   - `src/components/Duration.js` - Duration calculation logic
   - `src/components/GuestLimit.js` - Limit validation
   - `src/components/MaxAttendanceLimit.js` - Attendance calculations

#### Components Without Tests (16 total)

Most of these are UI-heavy React components that rely on WordPress hooks/state, making them harder to test. Focus on extracting pure functions from them rather than testing the components directly:

- AnonymousRsvp.js
- Autocomplete.js
- DateTimeEnd.js
- DateTimeRange.js
- DateTimeStart.js
- Duration.js
- EmailNotificationManager.js
- GoogleMap.js
- GuestLimit.js
- MaxAttendanceLimit.js
- OnlineEventLink.js
- OpenStreetMap.js
- Timezone.js
- UrlRewritePreview.js
- VenueInformation.js
- VenueSelector.js

#### Testing Strategy

**Philosophy**: Extract pure business logic into helper functions that can be easily unit tested. Leave UI components for integration/E2E testing.

**Benefits**:

- Easier to test complex logic in isolation
- Better separation of concerns
- Improved code reusability
- Higher test coverage with less test complexity

**Reference**: `src/blocks/form-field/helpers.js` is a good example - it has comprehensive tests at `test/unit/js/src/blocks/form-field/helpers.test.js`.
