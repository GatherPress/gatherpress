# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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

### JavaScript/TypeScript Guidelines

- **No console.log statements**: JavaScript linting will fail if `console.log()` statements are present in the code. For debugging purposes, use proper debugging tools or remove all console statements before committing.
- **E2E Tests**: E2E tests should focus on testing GatherPress functionality rather than WordPress UI implementation details. Tests should be resilient to development environment JavaScript errors.

### PHP Coding Standards

- **Use statements**: Always use `use` statements at the top of files for classes and functions instead of fully qualified namespace calls
    - ✅ Good: `use GatherPress\Core\Event;` then `new Event( $post_id )`
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
- **Type handling**: WordPress functions may return multiple types; handle all cases with proper type checking
    - Use `is_wp_error()`, `is_numeric()`, and similar WordPress/PHP functions
    - Cast types explicitly when needed: `(int) $comment->comment_post_ID`

### PHP Testing Guidelines

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
