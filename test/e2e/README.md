# GatherPress E2E tests

End-to-end tests for GatherPress, using [Playwright](https://playwright.dev/) against a real WordPress install.

This file covers the suite's internals. For getting it running (installing, starting `wp-env`, running a single spec, reading a failure), see the [contributor guide](../../docs/contributor/e2e-tests/README.md).

## Layout

```text
test/e2e/
├── global-setup.js        Logs in once over REST, writes storageState.json
├── storageState.json      The saved session every test reuses
├── admin-tests/           Admin screens load and the login works
├── event-tests/           Front-end event rendering, editor regressions
├── rsvp-tests/            RSVP flows (needs a seeded event, see below)
└── helpers/               Experimental event-creation helpers, not yet wired in
```

Every spec is a plain Playwright file. It requires `@playwright/test` and nothing else, and talks to the page directly:

```javascript
const { test, expect } = require( '@playwright/test' );
```

There is no page-object layer and no fixture factory. If you are adding a test, you have everything you need after that one `require`.

## Authentication

`global-setup.js` runs once before the suite. It uses `RequestUtils` from `@wordpress/e2e-test-utils-playwright` to authenticate over the REST API and save the session to `storageState.json`; `playwright.config.js` then hands that state to every test. Tests start already logged in as `admin`, and no test walks the login form.

If authentication fails for every test at once, delete `storageState.json` and run again. Global setup rebuilds it.

## The suites

### `admin-tests/`

`gatherpress-auth-test.spec.js` confirms the shared session actually works and that the GatherPress admin pages are reachable. `gatherpress-basic.spec.js` checks the events list, the venues list, the plugin's presence on the plugins screen, and the admin menu.

These are the canary tests. When they fail, it is usually the environment rather than the plugin.

### `event-tests/`

`event-display.spec.js` publishes an event and checks it renders on the front end.

`datetime-picker-regression.spec.js` guards [#1607](https://github.com/GatherPress/gatherpress/issues/1607): stepping the year *down* in the datetime picker crashed the editor. The test exists because unit tests could not see it. The crash only happened in a real editor with a real picker.

### `rsvp-tests/`

`rsvp-flows.spec.js` covers the RSVP surface: open RSVP by email while logged out, RSVP while logged in, status changes across attending / not attending / waiting list, the anonymous checkbox, guest counts, and the modal.

The tests are written and complete, but they are **skipped in CI**, because they need an event with an RSVP block and nothing seeds one yet. Run them by hand against an event you publish yourself:

```bash
EVENT_URL=http://localhost:8889/event/your-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js
```

Without `EVENT_URL` the suite fails immediately with setup instructions rather than reporting a misleading failure.

Getting them into CI means creating that event automatically. Three approaches are sketched in the spec's comments (a Playground blueprint importing WXR, driving the block editor with Playwright, or seeding directly with WP-CLI), and `helpers/` holds unfinished attempts at the second and third. None is wired into the suite.

## Conventions

**Select the way a person would.** Role, label and visible text survive a markup refactor; generated class names do not.

```javascript
// Good.
page.getByRole( 'button', { name: 'RSVP' } );

// Fragile.
page.locator( '.css-1a2b3c' );
page.locator( 'div > span:nth-child(3)' );
```

**Wait for a condition, not a duration.** Playwright's assertions retry until they time out, which is both faster and steadier than sleeping.

```javascript
// Good.
await expect( modal ).toBeVisible();

// Bad.
await page.waitForTimeout( 5000 );
```

**Make the data unique.** Two tests that both publish "Test Event" will collide as soon as they run in the same database. Add a timestamp.

**Clean up what you create**, so the next test starts from a known state.

**Fail with a message that names the problem.** `Expected title "Team standup", got "Auto Draft"` points at the bug; `Something went wrong` starts an investigation.

**Group long tests into steps**, so the report shows where it stopped:

```javascript
await test.step( 'publish the event', async () => { /* … */ } );
await test.step( 'RSVP as a logged-out visitor', async () => { /* … */ } );
```

**Name the test after the behavior.** "should show the waiting list once the event is full" tells you what broke. "test rsvp 3" does not.

## Constraints worth knowing

These are set in [`playwright.config.js`](../../playwright.config.js) and are deliberate:

- **One worker, no parallelism.** The tests share a single WordPress database and fought over it when run in parallel.
- **Generous timeouts** (180s per test, 30s navigation, 15s per action). WordPress admin is slow enough that Playwright's defaults produce false failures.
- **Artifacts on failure**: a screenshot always, plus a trace and video on the first retry.

## Still to do

- [ ] Seed the RSVP event automatically so `rsvp-tests/` can run in CI
- [ ] Front-end coverage beyond event display
- [ ] REST API tests
- [ ] Accessibility assertions
- [ ] Visual regression testing
- [ ] A cross-browser matrix. The suite is Chromium-only today
