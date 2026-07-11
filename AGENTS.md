# AGENTS.md

This file is the canonical project guide for AI coding agents (Claude Code, Cursor, Codex, Aider, etc.). The legacy `CLAUDE.md` path is a symlink to this file so Claude Code's auto-load picks up the same content other agents read — keep all guidance in `AGENTS.md`, never edit through the symlink.

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

- `gatherpress-venue-information` — **Core venue identifier.** Registers five individual editor-writable post meta keys (`gatherpress_address`, `gatherpress_latitude`, `gatherpress_longitude`, `gatherpress_phone`, `gatherpress_website`), all `show_in_rest`, so venue fields can be bound to blocks via `core/post-meta` block bindings. Also registers eight server-populated structured-address meta keys (`gatherpress_house_number`, `gatherpress_street`, `gatherpress_city`, `gatherpress_county`, `gatherpress_state`, `gatherpress_postcode`, `gatherpress_country`, `gatherpress_country_code`) derived from `gatherpress_address` by an async geocode cron handler — these are `show_in_rest` for read access only; REST writes are silently stripped. The unprefixed field list is the `Venue\Meta::STRUCTURED_ADDRESS_FIELDS` constant — single source of truth for registration, REST stripping, the cron write loop, and `Venue::get_information()`. (The companion editor-writable list lives at `Venue\Meta::EDITOR_WRITABLE_FIELDS`.) Meta registration itself lives on `Venue\Meta::register()`, hooked on `registered_post_type` so it fires once per supported post type; `Event\Meta::register()` mirrors the shape for event-date and event-only meta. The cron is gated by `gatherpress_geocode_on_save_enabled` (opt-out) and `gatherpress_async_geocode_pre_enqueue_job` (Action Scheduler short-circuit). Wires up the venue detail blocks. Meta revisions (`revisions_enabled`) are opted-in per post type and only applied when the post type declares `revisions` in its `supports` array. Declaring this support is what makes a post type a venue source. Implicitly declares `gatherpress-shadow-source` via `Venue\Setup::maybe_link_shadow_source_support()` (priority 9 on `registered_post_type`), so the venue's `_gatherpress_venue` taxonomy and term lifecycle wire up automatically without companion plugins having to declare both.
- `gatherpress-venue-map` — Map display meta (show/zoom/height) and the venue map block

**Shared primitives** (not specific to events or venues):

- `gatherpress-shadow-source` — Owned by `GatherPress\Core\Shadow_Source` (singleton at `includes/core/classes/class-shadow-source.php`). Registers a hidden `_<post_type>` taxonomy for any post type that declares the support and keeps one term per published post in lockstep with the post slug and title. Three lifecycle hooks: `save_post_<post_type>` inserts the term on first publish, the global `post_updated` action renames it when post_name/post_title change, and `delete_post_<post_type>` removes it. Term slugs are derived from `post_name` prefixed with an underscore (e.g. `my-venue` → `_my-venue`) — the leading underscore is the canonical signal that distinguishes real shadow terms from sentinel terms like the venue subsystem's `online-event`. Taxonomy labels are inherited from the source post type's labels (filterable via `gatherpress_shadow_taxonomy_args`). The shadow source primitive is what powers `gatherpress_venue` ⇄ event tagging; companion plugins can declare it directly on their own post types (productions, organizers, sponsors) to get the same behavior with no venue-specific baggage. Wiring the taxonomy onto consumer post types (events, sessions, etc.) is the developer's responsibility — pass it via `register_post_type`'s `taxonomies` arg or call `register_taxonomy_for_object_type()`. The venue subsystem performs that wiring for `gatherpress-venue` post types via `Venue\Setup::register_taxonomy()`.

**How it works:**

- `gatherpress_event` registers all event supports during `register_post_type()`
- `gatherpress_venue` registers all venue supports during `register_post_type()`
- PHP checks use `post_type_supports( $post_type, 'gatherpress-event-date' )` instead of `Event::POST_TYPE === $post_type`
- PHP checks use `post_type_supports( $post_type, 'gatherpress-venue-information' )` instead of `Venue::POST_TYPE === $post_type`
- Queries use `get_post_types_by_support( 'gatherpress-event-date' )` or `get_post_types_by_support( 'gatherpress-venue-information' )` instead of hardcoded post type slugs
- JS checks go through helpers in `src/helpers/event.js`: `isPostTypeSupporting( support, postType )` for imperative use, `usePostTypeSupports( support, postType )` (a `useSelect`-backed hook) when the result drives rendering. The non-reactive variant misses the post-type registry's first-render cache miss and leaves dim-gated blocks permanently dimmed in Query Loops — always reach for `usePostTypeSupports` inside React components
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
7. Update JS helpers to check supports via `isPostTypeSupporting` (imperative) or `usePostTypeSupports` (reactive — required when the check drives render output, including dim/opacity gates)
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

### Docblock conventions

Apply to PHP PHPDoc blocks and JS JSDoc blocks alike.

- **Never write `@since 1.0.0`**. 1.0.0 has not been released — every `@since` must resolve to a tag that exists in `git tag`. The tag floor is `0.27.0`; the current development line is `0.34.0`. New symbols added on `develop` past the latest tag take the next planned release version (i.e. whatever `0.34.0` resolves to today).
    - ✅ Good: `@since 0.33.0` (filter shipped in 0.33.0 stable).
    - ✅ Good: `@since 0.34.0` for anything introduced in the current dev cycle.
    - ❌ Bad: `@since 1.0.0`, `@since unreleased`, `@since TBD`.
- **Derive `@since` from git history, not memory.** When adding a new symbol, set `@since` to the target release. When touching an existing symbol whose `@since` looks wrong, verify against history before fixing:
    - Find the introducing commit: `git log --all --reverse -G "['\"]hook_name['\"]" --format=%H | head -1` for hooks, `git log --all --reverse -G "function method_name" -- path/to/file.php --format=%H | head -1` for methods.
    - Resolve to the first containing tag: `git describe --contains <sha>`.
    - Strip pre-release suffixes — `0.33.0-alpha.1` → `@since 0.33.0`. The `@since` tag tracks the **stable base version**, not the alpha/beta/rc the symbol first landed on.
    - Floor anything older than `0.27.0` to `0.27.0`.
    - Commits not yet in any tag map to the next release (currently `0.34.0`).
- **Signature changes after introduction don't move `@since`.** If a filter shipped in 0.30.0 and grew a third `@param` in 0.31.0, the docblock stays `@since 0.30.0`. Document the parameter evolution in a separate sentence or `@since` note inside the param's description — matches WordPress core convention.
- **Hook-name search must require quotes.** A PHP variable named `$gatherpress_template_path` shares a token with the filter `'gatherpress_template_path'`. When dating a hook from history, the search pattern needs the quote chars: `-G "['\"]hook_name['\"]"`. Bare-word matching picks up unrelated commits and dates the hook too early.
- **Canonical docblock shape** — short description first, then `@since` separated by a blank `* ` line, then the `@param` group separated by another blank `* ` line, then `@return` separated by another blank `* ` line:

    ```php
    /**
     * Method description.
     *
     * @since 0.30.0
     *
     * @param int[] $param_1 Param description.
     * @param int[] $param_2 Param description.
     *
     * @return int[] Description.
     */
    ```

    Rules:
    - Blank `* ` line between the short description and `@since`.
    - Blank `* ` line between `@since` and the `@param` group.
    - Blank `* ` line between the `@param` group and `@return`.
    - **No blank lines inside the `@param` group** — `@param` lines run consecutively.
    - Same shape for JS JSDoc (`@since`, `@param`, `@return`).
- **`@since` lives below the short description, not next to it.** ❌ Bad: `* Method description. @since 0.33.0` on one line. ✅ Good: description on its own line(s), blank `* ` separator, then `@since` on its own line.

### JavaScript/TypeScript Guidelines

- **No console.log statements**: JavaScript linting will fail if `console.log()` statements are present in the code. For debugging purposes, use proper debugging tools or remove all console statements before committing.
- **E2E Tests**: E2E tests should focus on testing GatherPress functionality rather than WordPress UI implementation details. Tests should be resilient to development environment JavaScript errors.

### PHP Coding Standards

- **Use statements**: Always use `use` statements at the top of files for classes and functions instead of fully qualified namespace calls
    - ✅ Good: `use GatherPress\Core\Event;` then `new Event( $post_id )`
    - ❌ Bad: `new \GatherPress\Core\Event( $post_id )`
    - For functions: `use function GatherPress\Core\filter_input;` then `filter_input( $value )`
- **Namespace resolution**: When moving code between namespaces, ensure proper imports are updated
- **Cross-subsystem use aliases**: When importing a class from another subsystem that shares a name with a local one (every subsystem has its own `Setup`, for example), alias it. Keeps the bare token unambiguously referring to the local same-namespace class and makes the cross-subsystem import announce itself at every call site.
    - ✅ Good (in `GatherPress\Core\Event\Admin_List`): `use GatherPress\Core\Venue\Setup as Venue_Setup;` → `Venue_Setup::get_instance()`
    - ❌ Bad (silently shadows the local `Event\Setup`): `use GatherPress\Core\Venue\Setup;` → `Setup::get_instance()`
    - Within one subsystem (e.g. `Event\Admin_List` referencing `Event\Query`), no alias needed.
- **Class body opens with a blank line**: Every `class|trait|interface Foo {` declaration is followed by an empty line before the first member, matching WordPress core's house style. Don't let the first docblock or `use` statement sit flush against the opening brace.
    - ✅ Good:

        ```php
        class Setup {

            /**
             * Enforces a single instance of this class.
             */
            use Singleton;
        ```

    - ❌ Bad: `class Setup {` immediately followed by `\t/**` on the next line.
- **Prefer `str_contains` / `str_starts_with` / `str_ends_with` over `strpos`**: WordPress ships polyfills for these PHP 8 string helpers back to PHP 7.0, so they're safe under our PHP 7.4 floor. They read better than the `false ===` / `0 ===` dance and SonarCloud flags the legacy form.
    - ✅ Good: `if ( str_contains( $haystack, $needle ) )` / `if ( str_starts_with( $key, 'gatherpress_' ) )` / `if ( ! str_contains( $content, $token ) )`
    - ❌ Bad: `if ( false !== strpos( $haystack, $needle ) )` / `if ( 0 === strpos( $key, 'gatherpress_' ) )` / `if ( false === strpos( $content, $token ) )`
- **Every `switch` needs a `default` case**: SonarCloud (`php:S131`) flags any switch missing a `default` branch, even when the listed cases cover the expected values. Add `default: break;` with a one-line comment explaining what falls through (e.g. "Field types without extra params render with the base $params.") — that way the reader sees the intent rather than wondering whether a case was forgotten.
    - ✅ Good:

        ```php
        switch ( $type ) {
            case 'text':
                // ...
                break;
            default:
                // Other field types use the base $params unchanged.
                break;
        }
        ```

    - ❌ Bad: closing the switch with the last `break;` and no `default:` arm.
- **Merge nested `if` statements when the outer body has nothing else** (`php:S1066`, "Mergeable 'if' statements should be combined"). If the inner `if` is the *only* statement in the outer block, hoist its condition with `&&`. Short-circuit semantics are identical, the indentation flattens, and SonarCloud stops nagging.
    - ✅ Good: `if ( 'on' === $switch && ! wp_next_scheduled( 'gatherpress_rsvp_cleanup' ) ) { ... }`
    - ❌ Bad: `if ( 'on' === $switch ) { if ( ! wp_next_scheduled( 'gatherpress_rsvp_cleanup' ) ) { ... } }`
    - Doesn't apply when the outer block has additional statements — keep them separate then.
- **Collapse duplicate `if`/`elseif` bodies into one conditional with `||`** (`php:S1871`, "Two branches in a conditional structure should not have exactly the same implementation"). When two arms run the same body, fold the conditions and add a comment if the original was preserving a non-obvious fallback.
    - ✅ Good (preserves the `Validate::datetime` fallback for the `timezone` key that the original `elseif` arm gave it):

        ```php
        // Timezone field validates as a tz string; datetime fields validate as a datetime.
        // The trailing datetime check still runs for the timezone key so a mistyped value
        // can fall back to datetime parsing, matching the prior elseif behavior.
        if (
            ( 'timezone' === $key && Validate::timezone( $result ) )
            || Validate::datetime( $result )
        ) {
            $data[ $key ] = $result;
        }
        ```

    - ❌ Bad: `if ( ... ) { $data[ $key ] = $result; } elseif ( ... ) { $data[ $key ] = $result; }` — same body twice.
    - Build a truth table before the merge — short-circuit-with-`||` doesn't always preserve the `if/elseif` chain's fallback semantics, especially when the second condition can re-evaluate after the first fails.
- **Drop dead `@SuppressWarnings(PHPMD.UnusedFormalParameter)` and inline `phpcs:ignore` comments when the parameter becomes used.** Suppressions are commitments to a known-bad state; once the underlying issue is fixed (e.g. the unused param is removed or starts being read), the suppression must go too. Leaving stale suppressions around hides future regressions of the same rule.
    - ✅ Good: `public function aql_query_vars( array $query_args, array $block_query ): array { ... }` — both params used, no annotation needed.
    - ❌ Bad: keeping `@SuppressWarnings(PHPMD.UnusedFormalParameter)` and `// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed` after the unused parameter has been removed.
- **Methods should have ≤3 `return` statements** (`php:S1142`, "This method has N returns, which is more than the 3 allowed"). Two patterns work depending on the shape of the function:
    - **Switch dispatches** — assign to a `$result` variable and return once at the end. Each `case` body sets `$result` and `break;` instead of returning.
    - **Guard chains** — combine multiple early bails into one `if (... || ... || ...)` with a single `return`. The `Why:` for each guard moves into a single explanatory comment above the merged condition rather than one comment per arm.
    - ✅ Good (switch dispatch with one trailing return):

        ```php
        $result = false;
        switch ( $config['type'] ) {
            case 'email':
                $sanitized = sanitize_email( $value );
                $result    = is_email( $sanitized ) ? $sanitized : false;
                break;
            // ... other cases
            default:
                $result = sanitize_text_field( $value );
                break;
        }
        return $result;
        ```

    - ✅ Good (combined guard):

        ```php
        // Skip non-shadow-source post types, updates, un-named or non-published
        // posts in one guard so the function reads top-down rather than as a
        // return chain.
        if ( ! post_type_supports( $post->post_type, 'gatherpress-shadow-source' )
            || $update
            || empty( $post->post_name )
            || 'publish' !== $post->post_status
        ) {
            return;
        }
        ```

    - ❌ Bad: a four-arm `if (X) return; if (Y) return; if (Z) return; if (W) return;` chain when nothing else lives between them — that's the shape S1142 flags.
    - **When NOT to merge:** if the bails surround `apply_filters` calls whose docblocks document the shape of each filter and matter to extension authors (e.g. `Geocoding::maybe_schedule_geocode`), keeping per-guard structure preserves the docs. Mark those instances won't-fix in SonarCloud rather than collapsing.
- **Reduce function cognitive complexity by extracting helpers from tight loops or repeated branches** (`php:S3776`, "Refactor this function to reduce its Cognitive Complexity from N to the 15 allowed"). Each `if`, `for`, `while`, or logical operator inside a loop costs more cognitive points the deeper it nests, so the cheapest reductions are the ones that pull a nested-2-deep block up to a helper called once.
    - ✅ Good (loop body extracted): the inner per-tile `fetch → decode → imagecopy` block in `Osm::render` moved into `paint_tile()`, dropping `render`'s complexity from 16 → ~10.
    - ✅ Good (per-recipient logic extracted): `Rest_Api::send_emails`'s per-recipient `opt-in / locale-switch / wp_mail` block moved into `send_event_email_to_recipient()`.
    - **Critical follow-up:** extracting a helper from a tight loop in the same class hits a known xdebug coverage gap — see the "Extracted same-class helpers and xdebug coverage tracing" rule in **Test Coverage** below. You must add a direct reflection-invoke test for every helper you extract.
- **WP callback signatures with required-but-unused params: mark won't-fix in SonarCloud, don't paper over with `unset()`** (`php:S1172`). When a parameter exists only to satisfy a WordPress hook signature (`auth_callback`, `added_post_meta` action, `register_meta` callbacks), the right answer is to mark the Sonar finding as a false positive in the Sonar UI. Do *not* add `unset( $allowed, $meta_key );` lines or rename to `$_allowed` to silence — those add noise without communicating the constraint, and reviewers don't know whether the workaround can be removed later. The existing `@SuppressWarnings(PHPMD.UnusedFormalParameter)` docblock plus a one-line "required by WP's X signature" comment is enough; the won't-fix in Sonar carries the rest.
    - ✅ Good: `public static function can_edit_post_meta( bool $allowed, string $meta_key, int $object_id, int $user_id ): bool { return user_can( $user_id, 'edit_post', $object_id ); }` plus a `@SuppressWarnings` docblock noting WP's contract — Sonar finding marked won't-fix.
    - ❌ Bad: `unset( $allowed, $meta_key );` as the first line of the function body.
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
- **PHPCS warnings count as failures**: `npm run lint:php` exits non-zero on warnings, not just errors. The most common one is `Generic.Files.LineLength.TooLong` (120-char limit). Don't dismiss warnings as cosmetic — fix them before declaring lint passes.
    - Long `@return array<string, array<...>>` PHPDoc types: extract the array shape into a `@phpstan-type` alias at the top of the class, then reference the alias.
    - Long expression lines: break at logical points (after `return`, after `=`, around operators) and indent the continuation with one tab beyond the statement start.
    - Long `sprintf()` / translator string args: split the format string across `__()` calls with concatenation, or store the format in a variable on its own line.

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

**Extracted same-class helpers and xdebug coverage tracing:** xdebug doesn't reliably trace lines inside `private` / `protected` methods that are called from a tight loop or short delegation in the same class. The method body executes (you can confirm with a `file_put_contents( '/tmp/probe.log', ... )` inside it) but `coverage.xml` reports the body as `count=0`. This bites every time you extract a helper for `php:S3776` cognitive-complexity reduction, and once for the inlined `current_screen_post_type()` helper in `Admin_List` before that — same shape, same gap.

- **The fix:** add a direct test that invokes the helper via `Utility::invoke_hidden_method( $instance, 'helper_name', array( ... ) )`. xdebug traces through that call cleanly even when it doesn't trace the same call from inside the parent function. Cover each branch of the helper this way (one test per `return` path).
- ✅ Good (after extracting `Osm::paint_tile` from `Osm::render`'s loop):

    ```php
    public function test_paint_tile_skips_when_fetch_returns_null(): void {
        // ... install pre_http_request filter that returns WP_Error
        $canvas = imagecreatetruecolor( 256, 256 );
        Utility::invoke_hidden_method(
            new OSM(),
            'paint_tile',
            array( $canvas, 0, 0, 1, 0, 0, 'https://example.test/{z}/{x}/{y}.png' )
        );
        $this->assertInstanceOf( GdImage::class, $canvas );
    }
    ```

- ❌ Bad: relying on the existing `test_render_*` tests to cover `paint_tile` transitively — they exercise the code path (the helper IS called) but xdebug records the helper body as uncovered, and the PR coverage gate fails.
- **Apply this proactively**, not just when the gate fails: any time you extract a helper for cognitive-complexity reduction, add the direct invokes in the same PR.
- **Two cases that genuinely need `@codeCoverageIgnore`** even after adding direct invokes: (1) WP-locale-switcher cleanup branches like `if ( $switched_locale ) { restore_previous_locale(); }` — `switch_to_user_locale()` returns false in the test runner regardless of `get_user_locale` filters because the test env's `WP_Locale_Switcher` is stubbed; (2) classic-theme guards like `if ( ! function_exists( 'get_block_templates' ) ) { return; }` — the function always exists in the WP test bootstrap. Mark these with a short comment explaining what's untestable.

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
- **Dependency-section docblocks have no trailing period**: The `WordPress dependencies` / `Internal dependencies` / `External dependencies` (and `Mock …` test variants) section headers above import groups are labels, not sentences — leave the period off.
    - ✅ Good:

        ```js
        /**
         * WordPress dependencies
         */
        import { __ } from '@wordpress/i18n';
        ```

    - ❌ Bad: `* WordPress dependencies.` (with the period)
- **Optional chaining over `&&` / `||` guard chains**: SonarCloud (`javascript:S6582`) flags multi-step nullable property access dressed up as `a && a.b && a.b.c` (positive guard) or `! a || ! a.b` (negated guard). Use `?.` instead.
    - ✅ Good: `if ( ! window.wp?.date ) { return; }` / `if ( ! settings?.timezone?.string ) { return; }` / `if ( 'TEXTAREA' !== el?.nodeName ) { return; }` / `if ( 'publish' === post?.status ) { ... }` / `if ( ! terms?.length ) { return 'in-person'; }`
    - ❌ Bad: `if ( ! window.wp || ! window.wp.date )` / `if ( ! settings || ! settings.timezone || ! settings.timezone.string )` / `if ( ! el || 'TEXTAREA' !== el.nodeName )` / `if ( post && 'publish' === post.status )` / `if ( ! terms || ! terms.length )`
- **No empty-object-spread fallback** (`javascript:S6661`, "The empty object is useless"). Spreading `null` or `undefined` is already a no-op in modern JS — `{ ...maybeObj }` works whether `maybeObj` is `undefined`, `null`, or an object. Drop the `|| {}`.
    - ✅ Good: `const next = { ...attributes.metadata }` / `meta: { ...current.meta, gatherpress_static_map: response?.descriptors || {} }`
    - ❌ Bad: `const next = { ...( attributes.metadata || {} ) }` / `meta: { ...( current.meta || {} ), ... }`
    - The `|| {}` / `|| []` is still load-bearing for *array spreads* into a non-spread context (`Array.from(maybeArr || [])`), or when you actually need the empty object as a return value — only the spread-followed-by-`|| {}` form is dead.
- **Prefer `Array.prototype.at(-1)` over `arr[ arr.length - 1 ]`** (`javascript:S6582`). Cleaner and avoids the manual length math. Same for `at( -2 )`, etc.
    - ✅ Good: `return parents.at( -1 );`
    - ❌ Bad: `return parents[ parents.length - 1 ];`
- **Use `RegExp.exec()` over `String.match()` when capturing groups from a non-global pattern** (`javascript:S6594`). Same return shape (match array or `null`), but the static-analyzer reads the intent better and SonarCloud stops flagging.
    - ✅ Good: `const match = /^(\d+)\s*[/:]\s*(\d+)$/.exec( ratio.trim() );`
    - ❌ Bad: `const match = ratio.trim().match( /^(\d+)\s*[/:]\s*(\d+)$/ );`
- **Optional catch binding for intentionally swallowed exceptions** (`javascript:S2486`, "Either log this exception or rethrow it"). Drop the unused `( e )` and use the bare `catch { ... }` form (ES2019). The no-binding form is the canonical "this is intentional" signal — ESLint won't complain and SonarCloud accepts it because there's no captured exception that gets ignored. The project's no-console policy means logging from inside the catch is usually off the table, so this is the standard fix.
    - ✅ Good:

        ```js
        try {
            attrs = JSON.parse( container.dataset.gatherpress_block_attrs );
        } catch {
            // Malformed JSON — leave the static baseline in place.
            continue;
        }
        ```

    - ❌ Bad: `} catch ( e ) { /* swallow */ continue; }` — captured exception that's then ignored.
- **`element.dataset.foo` over `element.setAttribute( 'data-foo', ... )`** (`javascript:S6747`, "Prefer `.dataset` over `setAttribute`"). The `dataset` accessor is faster, type-coerces consistently, and reads better. Only use `setAttribute` for non-`data-*` attributes (`autocomplete`, ARIA attrs, `role`, etc.).
    - ✅ Good: `el.dataset.lpignore = 'true';` / `el.dataset.formType = 'other';` / `el.dataset[ '1pIgnore' ] = 'true';`
    - ❌ Bad: `el.setAttribute( 'data-lpignore', 'true' );` / `el.setAttribute( 'data-form-type', 'other' );`
    - Bracket notation is required when the dataset key starts with a digit (e.g. `data-1p-ignore` exposes as `1pIgnore`, which isn't a valid identifier for dot access).
- **No useless `try { ... } catch ( e ) { throw e; }` wrappers** (`javascript:S2737`, "Catch clauses should do more than rethrow"). If the catch only re-throws the same error with no logging, transformation, or cleanup, delete the entire `try`/`catch` and let the exception propagate naturally. Same control flow, less code.
    - ✅ Good: `return apiFetch( { path: ..., method: 'POST', data: ... } );`
    - ❌ Bad: `try { return await apiFetch( ... ); } catch ( error ) { throw error; }`
    - If you remove a try/catch wrapper that only existed for a now-deleted log statement, also drop the `async` keyword if the function no longer has an internal `await` — see the "no redundant `async`" rule below.
- **`javascript:S4123` ("`await` of a non-Thenable") needs a per-case decision**: Sonar's flow analysis is finicky about whether it can prove a function returns a Promise. Two sub-cases, and the right fix is different for each:
    - **(a) Function is `async` with no internal `await`** (e.g. `async (a, b) => { return apiFetch({...}); }`): drop the `async`. The function still returns a Promise (via `apiFetch`) so callers' `await fn()` keeps working, and Sonar accepts the await on the Promise return type.
        - ✅ Good: `const createNewVenuePost = ( a, b ) => apiFetch( { ... } );`
        - ❌ Bad: `const createNewVenuePost = async ( a, b ) => { return apiFetch( { ... } ); }`
    - **(b) Function is non-`async` and explicitly returns a Promise-returning call** (e.g. `(a, b) => apiFetch({...})`) **and Sonar still flags the await at the call site**: the JSDoc `@return {Object}` (or no `@return` at all) isn't enough for Sonar to infer Promise. Add the `async` keyword back. The "no redundant async" intuition fails here because Sonar is reading the type from the function signature, not the body. Update `@return` to `{Promise<Object>}` while you're there.
        - ✅ Good: `const createNewVenuePost = async ( a, b ) => apiFetch( { ... } );` with `@return {Promise<Object>}`
        - ❌ Bad: leaving the function non-`async` with `@return {Object}` while Sonar still flags two `await createNewVenuePost(...)` call sites.
    - **Compare against a peer** before deciding which sub-case applies: if `geocodeAddress` (declared `async function`) is awaited next to `createNewVenuePost` and only the latter trips S4123, you're in case (b).
- **No fragments with a single child** (`javascript:S6749`). Just return the child directly.
    - ✅ Good: `return ( <ComboboxControl ... /> );`
    - ❌ Bad: `return ( <><ComboboxControl ... /></> );`
- **Flip negated `if`/`else` conditions so the positive arm comes first** (`javascript:S2310`, "Unexpected negated condition"). Easier to read and SonarCloud stops flagging. For two-branch ternaries, swap the arms; for `if/else` blocks, flip the condition and swap the bodies. For pure boolean choices a ternary is often cleaner than nested `if/else` (e.g. `newTerms = hasTermAlready ? currentTerms : [ ...currentTerms, termId ];` instead of `if ( ! hasTermAlready ) { ... } else { ... }`).
    - ✅ Good: `if ( 'loading' === document.readyState ) { document.addEventListener( ... ); } else { initTooltips(); }`
    - ❌ Bad: `if ( 'loading' !== document.readyState ) { initTooltips(); } else { document.addEventListener( ... ); }`
- **Every arm of a callback (`useSelect`, reducers, mappers) must return the same shape** (`javascript:S3801`, "Refactor this function to always return the same type"). When the early-bail arm returns `[]` and the happy-path arm returns `{ key: value }`, downstream destructures (`const { key } = useSelect( ... )`) silently yield `undefined` from the `[]` arm — that's a latent bug, not a stylistic issue. Unify both arms to the object shape.
    - ✅ Good:

        ```js
        useSelect( ( select ) => {
            if ( null === id ) {
                return { venuePost: undefined };
            }
            return { venuePost: select( 'core' ).getEntityRecords( ... ) };
        }, [ id ] );
        ```

    - ❌ Bad: `if ( null === id ) { return []; } return { venuePost: ... };` — destructure of `[]` gives `undefined`, accidentally matching the intent but for the wrong reason.
- **Hooks must start with `use` — never PascalCase** (`react-hooks/rules-of-hooks`). Any function calling `useSelect` / `useState` / `useEffect` / etc. is a hook and the linter only enforces the rules-of-hooks invariants when the name starts with `use`. PascalCase names like `GetVenuePostFromTermId` (which uses `useSelect` internally) are silently exempted from the linter and risk hook-order violations going undetected.
    - ✅ Good: `export function useVenuePostFromTermId( termId, ... ) { const { venuePost } = useSelect( ... ); ... }`
    - ❌ Bad: `export function GetVenuePostFromTermId( termId, ... ) { const { venuePost } = useSelect( ... ); ... }` — looks like a regular helper, isn't.
    - When renaming, also update *every* call site (block edits, slotfills, tests) — and check tests for `renderHook( () => OldName( ... ) )` patterns.
- **Drop deprecated `__nextHasNoMarginBottom` from `@wordpress/components` 32+**: The "no margin bottom" behavior became the default in v29, and the prop is now a deprecated no-op. Just remove the line. If SonarCloud flags `'__nextHasNoMarginBottom' is deprecated`, the fix is one-line. The same will eventually apply to other `__next*` opt-in flags (`__next40pxDefaultSize`, `__nextHasNoMarginTop`, etc.) — only remove what Sonar / the WP changelog actually marks deprecated, since some are still opt-ins on this version.
    - ✅ Good: `<ToggleGroupControl label={ ... } isBlock __next40pxDefaultSize onChange={ ... }>`
    - ❌ Bad: `<ToggleGroupControl label={ ... } isBlock __nextHasNoMarginBottom __next40pxDefaultSize onChange={ ... }>`
- **Stable `Navigator` over `__experimentalNavigatorProvider`** (and the rest of the `__experimentalNavigator*` family). The stable API ships under `Navigator` with the same props (`initialPath`, etc.) and exposes subcomponents as `Navigator.Screen`, `Navigator.Button`, `Navigator.BackButton`. If a file imports both the stable `Navigator` and the experimental `__experimentalNavigatorProvider as NavigatorProvider`, the migration is half-done — drop the experimental import and rename the wrapper.
    - ✅ Good: `import { Navigator } from '@wordpress/components';` then `<Navigator initialPath="/">...</Navigator>`
    - ❌ Bad: `import { __experimentalNavigatorProvider as NavigatorProvider, Navigator } from '@wordpress/components';` then `<NavigatorProvider initialPath="/">...</NavigatorProvider>`

### Accessibility

A11y rules that fire frequently and have known-good fixes specific to this codebase:

- **`<output>` over `<div role="status">`** (`Web:S6850`). `<output>` has implicit `role="status"`, so the swap is semantically equivalent and shorter markup. Same `aria-live="polite"` behavior for assistive tech.
    - ✅ Good: `<output className="...">{ message }</output>`
    - ❌ Bad: `<div className="..." role="status">{ message }</div>`
- **Layout `<table>` → CSS grid (`gatherpress-settings-form` BEM pattern)** (`Web:S5257`, "HTML `<table>` should not be used for layout purposes"). Even with `role="presentation"`, SonarCloud still flags layout tables. Replace with the established BEM pattern in `includes/templates/admin/settings/`:

    ```html
    <div class="gatherpress-settings-form">
        <div class="gatherpress-settings-form__row">
            <div class="gatherpress-settings-form__label">Label</div>
            <div class="gatherpress-settings-form__field">Field</div>
        </div>
        <!-- For colspan="2" rows that should span both columns: -->
        <div class="gatherpress-settings-form__row gatherpress-settings-form__row--full">
            <fieldset>...</fieldset>
        </div>
    </div>
    ```

    The CSS is class-based (`display: grid; grid-template-columns: 200px 1fr;` with a 782px mobile breakpoint that stacks). It currently lives inline in `network-page.php` and `tools.php` — duplicate it inline if you need it on a third settings template, or extract to a shared admin CSS file if it grows further. Tables emitted by `do_settings_sections()` (WP core) are out of scope — those need custom section/field renderers to reshape, which isn't worth doing for a Sonar finding.
- **ARIA combobox-with-listbox-popup pattern is correct for autocomplete — file Sonar's `<select>`/`<datalist>` suggestion as won't-fix** (`Web:S6817` and friends). Sonar's recommendations don't fit free-text autocomplete with custom-rendered items. The W3C ARIA Authoring Practices Guide combobox pattern is what this codebase uses and what AT expects. Required wiring on the input element:
    - `role="combobox"`, `aria-autocomplete="list"`, `aria-expanded={ panelOpen }`, `aria-controls={ panelOpen ? listboxId : undefined }`, `aria-activedescendant={ activeId }`, plus an accessible name (`aria-label` or wrapped `<label>`).
    - The popup uses `<div role="listbox" id={ listboxId } aria-label="...">` (use a `<div>`, not `<ul>` — see next bullet) with each option as `<button role="option" id={ optionId } aria-selected={ isActive } tabIndex={ -1 }>`.
    - When marking won't-fix in SonarCloud, paste the rationale: *"ARIA combobox-with-listbox-popup pattern (W3C APG). Native `<input list>`/`<datalist>` are single-line and can't render custom items; `<select multiple>` is for static-options selection, not free-text autocomplete."*
- **Use `<div role="listbox">` rather than `<ul role="listbox">`** (`jsx-a11y/no-noninteractive-element-to-interactive-role`). The lint rule fires on the implicit-role-of-`<ul>`-being-overridden, not on the listbox role itself. Switching to a `<div>` with `<button role="option">` children (no wrapping `<li role="none">` needed) avoids the rule entirely. CSS targeting class names rather than tags makes this a free swap — verify before touching markup that styles aren't `ul.foo` / `li`-scoped.
- **Don't slap `role="listbox"` on a plain list of standalone clickable items**. If the children aren't `role="option"` and there's no combobox driving the list (no `aria-controls`/`aria-activedescendant` from an input), the role is incomplete-and-cosmetic — just delete it. A `<ul>`/`<li>`/`<button>` markup is already correct, accessible "list of clickable items," and users navigate with Tab. Don't add ARIA you don't intend to wire up fully.

## Release Process and Branch Model

The full release runbook lives at [`docs/contributor/release-process.md`](docs/contributor/release-process.md). The rules below are the invariants agents must respect in day-to-day work:

- **`develop` is the trunk; `main` is the released state.** All feature/fix PRs target `develop` and get **squash-merged** (branch protection enforces linear history and signed commits). Only release-train PRs target `main` (the develop→main release merge, patch `version-X.Y.N` branches, changelog parity syncs).
- **Never squash a develop→main release PR** — it must merge with a merge commit or the branch histories permanently diverge. Conversely, PRs into `develop` are always squashed.
- **Every PR into `develop` needs changelog handling**: either a `.github/changelog/` entry file (Significance/Type header + one-line message; generate one with `composer changelog:add`, or copy the format from entries in git history — the directory is empty right after a release) or the `Skip Changelog` label. Convention: user-visible changes get an entry; docs-only, CI-only, dev-dependency, and version-bump PRs get the label. The gate only enforces on PRs based on `develop`.
- **Generated files — never hand-edit**: `README.md`, `readme.txt`, `includes/data/credits.php`, `SECURITY.md`, and all version strings are written by the release tooling (`wp gatherpress develop generate_version`, being ported to `.github/scripts` by #1827). `docs/developer/hooks/` is regenerated by CI (see above). Feature-list changes go in `docs/features.md` here and in the gatherpress-develop repo's `parts/shared/features.md` for the generated README.
- **Distribution has two allowlists**: the GitHub release zip uses `package.json`'s `files` field; the wp.org deploy uses `.distignore`. A new dev/config/tooling file at the repo root must be added to `.distignore` or it ships to wp.org (see #1920 for the precedent).
- **Patch fixes are born on `develop`** and cherry-picked (`git cherry-pick -x`) to the `version-X.Y.N` branch off `main`. Don't write original fixes on a patch branch unless the bug no longer exists on develop.
- **After any release, two loop-closing steps are mandatory**: confirm the auto-opened `release/X.Y.Z` rollup PR auto-merged into develop (its commit is API-signed and auto-merge is pre-enabled; intervene only if it's stuck), and cherry-pick that rollup onto `main` for changelog parity. The release workflow refuses to run a stable tag while a `release/*` PR is still open, so an unmerged rollup blocks the next release instead of double-rolling its changelog.
- **Agents must not push release tags.** A stable tag push deploys to WordPress.org; that call belongs to the release manager.
- **GatherPress Alpha** (sibling repo/checkout `../gatherpress-alpha`) is version-locked to core and refuses to run on mismatch — every core version bump needs its lockstep sync PR.

## Known Issues / Technical Debt

### @wordpress/env Patched via patch-package

**State**: `@wordpress/env` is on `^11.1.0` with a local patch at `patches/@wordpress+env+11.2.0.patch` applied during `npm install` via the `postinstall` hook (uses [`patch-package`](https://www.npmjs.com/package/patch-package)). The patch adds two `composer global config` lines before the wp-env Docker image's `composer global require phpunit` step so Composer's audit does not block install on advisories `PKSA-5jz8-6tcw-pbk4` / `PKSA-z3gr-8qht-p93v`.

**Why it's needed**: Without the patch, the wp-env Docker build fails before the test container is ready, which breaks `npm run test:unit:php` and the CI workflows that use it (`phpunit-tests.yml`, `sonarcloud.yml`, `pr-coverage.yml`, `e2e-tests.yml`).

**Action Required**:

- Monitor [@wordpress/env releases](https://www.npmjs.com/package/@wordpress/env) for an upstream fix that disables / configures Composer audit, or a PHPUnit pin that no longer trips the advisories.
- When such a release lands, bump `@wordpress/env`, delete `patches/@wordpress+env+*.patch`, and remove the `postinstall` hook + `patch-package` dependency from `package.json` if no other patches remain.
- Removal procedure and full rationale live in [`patches/README.md`](patches/README.md) — keep that file in sync with whatever lives under `patches/`.

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
