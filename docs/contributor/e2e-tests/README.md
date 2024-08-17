## E2E Testing using Playwright and `wp-now`

GatherPress allows to run **automated & manual end-to-end tests**, while sharing the same, `wp-now` based, setup.

## Automated tests

Check the results of the _e2e-tests action workflow_ at  `https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml`.

## Manual testing

### Install dependencies

To run the E2E tests you will have to install playwright using the following command:

```bash
npx playwright install --with-deps
```

> [!NOTE]
> You also need to use Node.js 20 or later

Create the testing instance using the following command:

```bash
npm ci --legacy-peer-deps
```

This will create a `wp-now` WordPress instance. The port is `8889` and the user is `admin` and the password is `password` (the same values used by `wp-env` testing instance).

### How to run the E2E tests

_Choose one of the following options_

1. For the _headless_ mode, use the following command:

   ```bash
   npm run test:e2e
   ```

2. Run Playwright _visually_ (to run tests in isolation and change what's happening), use:

   ```bash
   npm run test:e2e:ui
   ```
   ![grafik](https://github.com/user-attachments/assets/1627dff7-363e-447e-9981-adac610ac888)


3. For _debug_ mode (which will open the browser along with Playwright Editor and allows you to record what's happening), use the following command:

   ```bash
   npm run test:e2e:debug
   ```

   > [!NOTE]
   > When writing a test, using the debug mode is recommended since it will allow you to see the browser and the test in action.

4. Run Tests independently _AND_ visually using the [Playwright VSCode extension](https://playwright.dev/docs/getting-started-vscode)


### Learn more about E2E testing

Resources:

- [Playwright Documentation](https://playwright.dev/docs/intro)
- https://github.com/WordPress/gutenberg/blob/trunk/packages/e2e-test-utils-playwright/README.md
- https://developer.wordpress.org/block-editor/contributors/code/testing-overview/e2e/
- https://developer.wordpress.org/block-editor/contributors/code/testing-overview/e2e/overusing-snapshots/
- https://wordpress.com/blog/2023/05/23/wp-now-launch-a-local-environment-in-seconds/
- https://www.npmjs.com/package/@wp-now/wp-now

To see more examples of E2E tests, check the Gutenberg repository: https://github.com/WordPress/gutenberg/tree/trunk/test/e2e

> [!NOTE]
> If you are out of ideas on who to test, check the Gutenberg repository. It has a lot of examples of E2E tests that you can use as a reference.