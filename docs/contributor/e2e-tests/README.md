## End-to-end Testing using Playwright & Playground

GatherPress allows to run **automated & manual end-to-end tests** (e2e), while sharing the same, [`wp-playground/cli`](https://github.com/WordPress/wordpress-playground/pull/1289) powered, setup. The started playground imports the [`GatherPress/demo-data`](https://github.com/GatherPress/demo-data), that can be used instead of mocks or fixtures.

## Automated tests

Check the results of the [_e2e-tests action workflow_](https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml) at  `https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml`.

## Manual testing

The e2e-setup can also be used to **manually run the test suite**. In general, only a Playground instance is needed.

### Install dependencies

To run the e2e tests you will have to install playwright using the following command:

```bash
npx playwright install --with-deps
```

> [!NOTE]
> You also need to use Node.js 20 or later

Install the dependencies to create the Playground testing instance, using the following command:

```bash
npm ci --legacy-peer-deps
```

### Start the Playground

A call to `npm run playground:mount` will automatically setup a `wp-playground/cli` powered WordPress instance, already prepared to mount GatherPress from the current directory.


```bash
npm run playground:mount -- --blueprint=./test/e2e/blueprint.json
```

The testing website is reachable at `http://127.0.0.1:9400`, the user is `admin` and the password is `password`. 

### Run the e2e tests

Now while Playground is running in the background, it's possible to start the testing.

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

   Run all the tests against a specific project.
   ```bash
   npm run test:e2e:debug -- project=webkit
   ```

   Run files that have *events.spec* in the file name.
   ```bash
   npm run test:e2e:debug -- events.spec
   ```

   > [!NOTE]
   > When writing a test, using the debug mode is recommended since it will allow you to see the browser and the test in action.

4. Run Tests independently _AND_ visually using the [Playwright VSCode extension](https://playwright.dev/docs/getting-started-vscode)


### More about e2e testing

#### Start here:

- [Playwright Documentation](https://playwright.dev/docs/intro)
- [End-To-End Playwright test utils for WordPress](https://github.com/WordPress/gutenberg/blob/trunk/packages/e2e-test-utils-playwright/README.md)

#### from the WordPress handbooks

- [End-to-End Testing – Block Editor Handbook | Developer.WordPress.org](https://developer.wordpress.org/block-editor/contributors/code/testing-overview/e2e/)
- [Overusing snapshots – Block Editor Handbook | Developer.WordPress.org](https://developer.wordpress.org/block-editor/contributors/code/testing-overview/e2e/overusing-snapshots/)

### More about `wp-playground/cli`, as the testing environment

Examples with great documentation:

- [Playground CLI · WordPress/wordpress-playground#1289](https://github.com/WordPress/wordpress-playground/pull/1289)
- [PoC: Run e2e tests with WP Playground · WordPress/gutenberg#62692](https://github.com/WordPress/gutenberg/pull/62692)
- [Use WordPress Playground · swissspidy/wp-performance-action#173](https://github.com/swissspidy/wp-performance-action/pull/173)

To see more examples of e2e tests, check the Gutenberg repository: https://github.com/WordPress/gutenberg/tree/trunk/test/e2e

> [!NOTE]
> If you are out of ideas on who to test, check the Gutenberg repository. It has a lot of examples of e2e tests that you can use as a reference.