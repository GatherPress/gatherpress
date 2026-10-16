# End-to-end tests

GatherPress runs **end-to-end tests** with [Playwright](https://playwright.dev/), driving a real browser against a real WordPress install. Where the [PHP unit tests](../unit-tests/README.md) check a class in isolation, these check that a person can actually complete a task: log in, open the events list, publish an event, RSVP to it.

The suite shares its `wp-env` setup with the [screenshot generator](../screenshot-generator/README.md), which is also Playwright-driven.

## Automated tests

Check the results of the [*E2E Tests* workflow](https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml).

It runs on every push to `main` and `develop`, and on pull requests that touch `includes/`, `src/`, `test/e2e/`, the workflow file, or any root-level PHP or JavaScript file. Runs are capped at 30 minutes and a newer push to the same branch cancels the one in progress.

> [!NOTE]
> CI runs against the Google Chrome preinstalled on the GitHub runner rather than Playwright's own Chromium download, because that download reliably stalls after reaching 100% on the runners. Local runs use the downloaded Chromium, so you do not need Chrome installed.

## Running the tests locally

### Install dependencies

```bash
npm install
```

Then install the browser Playwright drives:

```bash
npx playwright install chromium --with-deps
```

### Start the environment

You do not have to start anything by hand. `npm run test:e2e` is preceded by a `pretest:e2e` script that starts `wp-env` with [`.wp-env.test.json`](../../../.wp-env.test.json), a slim config that mounts the plugin, activates Twenty Twenty-Five, and serves on port **8889**. If an instance is already running the tests use it.

The test site is at `http://localhost:8889`, the user is `admin` and the password is `password`.

Authentication happens once, in [`test/e2e/global-setup.js`](../../../test/e2e/global-setup.js). It logs in over the REST API using `@wordpress/e2e-test-utils-playwright` and writes the session to `test/e2e/storageState.json`, which every test then reuses. No test types a password.

### Run the suite

```bash
npm run test:e2e
```

A single file:

```bash
npm run test:e2e -- event-tests/event-display.spec.js
```

Watch it happen in a visible browser:

```bash
npm run test:e2e -- --headed
```

Step through a failure interactively:

```bash
npm run test:e2e -- --debug
```

Point the suite at a different site:

```bash
WP_BASE_URL=http://localhost:8889 npm run test:e2e
```

## What the suite covers

Tests live in [`test/e2e/`](../../../test/e2e/), grouped by what they exercise:

| Directory | Covers |
|---|---|
| `admin-tests/` | That the shared login works, and that the events list, venues list, plugins screen and GatherPress admin menu all load |
| `event-tests/` | That a published event renders on the front end, plus a regression guard for [#1607](https://github.com/GatherPress/gatherpress/issues/1607), where stepping the year down in the datetime picker crashed the editor |
| `rsvp-tests/` | The RSVP flows: open RSVP by email while logged out, RSVP while logged in, status changes, the anonymous checkbox, guest counts and the modal |

Each spec is a plain Playwright file that requires only `@playwright/test` and talks to the page directly. There is no page-object layer or fixture factory to learn before writing one.

### The RSVP tests need an event

`rsvp-tests/rsvp-flows.spec.js` is complete but does not run in CI, because it needs an event with an RSVP block and nothing creates one automatically yet. It fails fast with instructions if you run it without one.

To run it, publish an event dated at least a week out, add the RSVP block, then pass its URL:

```bash
EVENT_URL=http://localhost:8889/event/your-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js
```

Getting these into CI means seeding that event automatically. Three approaches are sketched in the spec's own comments (a Playground blueprint importing WXR, driving the editor with Playwright, or seeding the database with WP-CLI), and there are experimental helpers in `test/e2e/helpers/` for reference.

## Configuration

[`playwright.config.js`](../../../playwright.config.js) holds the settings worth knowing:

- **One worker, no parallelism.** WordPress tests share one database, and parallel runs fought over it.
- **Retries**: twice in CI, once locally.
- **Timeouts**: 180s per test, 30s for navigation, 15s for an action, 10s for an expectation. WordPress admin is slow enough that the defaults produce false failures.
- **On failure**: a screenshot. **On the first retry**: a trace and a video.
- **Reporters**: an HTML report, a stepped list in the terminal, and JUnit XML at `test-results/junit.xml`.

## Debugging a failure

Open the HTML report from the last run:

```bash
npx playwright show-report
```

Or replay a failing test as a trace, which gives you the DOM, console and network at every step:

```bash
npx playwright show-trace test-results/<test-name>/trace.zip
```

For more detail while the test runs:

```bash
DEBUG=pw:api npm run test:e2e
```

| Symptom | Likely cause | Fix |
|---|---|---|
| Everything fails at login | Stale or corrupt session | Delete `test/e2e/storageState.json` and re-run; global setup rebuilds it |
| One selector never resolves | Markup changed | Update the selector, and prefer a role or label over a class |
| Timeout on page load | Slow admin, not a bug | Raise `navigationTimeout` in the config |
| Passes alone, fails in the suite | Leftover data from an earlier test | Clean up what the test created |
| `EVENT_URL environment variable is required` | Running the RSVP specs without an event | See [above](#the-rsvp-tests-need-an-event) |

## Writing a test

Keep it close to what a person does, and assert on what they would see.

```javascript
const { test, expect } = require( '@playwright/test' );

test.describe( 'Event display', () => {
	test( 'shows the event title on the front end', async ( { page } ) => {
		await page.goto( '/?post_type=gatherpress_event' );

		await expect(
			page.getByRole( 'heading', { name: 'Team standup' } )
		).toBeVisible();
	} );
} );
```

A few things that keep these tests from turning flaky:

- **Select by role, label or text**, not by generated class names. `page.getByRole( 'button', { name: 'RSVP' } )` survives a markup refactor; `.css-1a2b3c` does not.
- **Wait for a condition, never a duration.** `await expect( thing ).toBeVisible()` retries until the timeout; `waitForTimeout( 5000 )` is both slower and less reliable.
- **Make the data unique.** Two tests that both publish "Test Event" will eventually collide. Add a timestamp.
- **Clean up what you create**, so the next test starts from a known state.
- **Name the test after the behavior**, not the mechanics: "should show the waiting list when the event is full" tells you what broke; "test rsvp 3" does not.

For a longer walkthrough of the suite's internals, see [`test/e2e/README.md`](../../../test/e2e/README.md).
