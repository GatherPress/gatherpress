# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### PHP Development
- `composer lint` - Run PHP CodeSniffer linting
- `composer format` - Auto-fix PHP coding standards issues  
- `composer test` - Run PHP unit tests
- `composer test:phpstan` - Run PHPStan static analysis
- `composer compat` - Check PHP compatibility

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
- `npm run wp-env start --xdebug` - Start WordPress environment with debugging

### WordPress Environment
- `npm run wp-env` - Manage local WordPress environment
- `npm run playground` - Start WordPress Playground server
- `npm run plugin-zip` - Create distributable plugin zip

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

When working with this codebase:
1. Always run linting before committing
2. Use existing WordPress hooks and filters patterns
3. Follow WordPress coding standards
4. Test both PHP and JavaScript components
5. Consider block editor compatibility when making changes