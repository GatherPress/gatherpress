## Generate Screenshots using Playwright & Playground

> Generating screenshots in multiple languages for the plugin and keeping them up to date with the development might become a time intensive task.

GatherPress allows to generate screenshots for the plugin **automated & manually**, while sharing the same, [`wp-playground/cli`](https://github.com/WordPress/wordpress-playground/pull/1289) powered, setup. The started playground imports the [`GatherPress/demo-data`](https://github.com/GatherPress/demo-data) and sets some options to hide GatherPress' admin-notices.

GatherPress uses Playwright for this, which is currently used as tool to do [end-to-end testing](../e2e-tests). Playwright has an advanced [screenshots API](https://playwright.dev/docs/screenshots), that allows to take screenshots for a full-page or an element only. Like for the e2e tests, it is configurable what browsers to use and what language to use in WordPress.

GatherPress defined a set of screenshots to generate and get those generated

- automatically using GitHub actions
- (On every major and minor, but not on bug-fix, releases) *currently done manually via `workflow_dispatch`*
- in all languages, with more than 90% of finished translations
- with pixel-perfect consistency across releases & languages

## Automated Screenshots

...

## Manually generating Screenshots

### Install dependencies

To generate screenshots you will have to install playwright using the following command:

```bash
npx playwright install --with-deps
```

> [!NOTE]
> You also need to use Node.js 20 or later

Install the dependencies to create the Playground instance, using the following command:

```bash
npm ci --legacy-peer-deps
```

### Run the Screenshot generator

A call to `...` will automatically setup a `wp-playground/cli` powered WordPress instance.

The testing is website is reachable at `http://127.0.0.1:9400`, the user is `admin` and the password is `password`. 

_Choose one of the following options_

1. For the _headless_ mode, use the following command:

   ```bash
   npm run screenshots:wporg
   ```

2. Run Playwright _visually_ (to run generating screenshots in isolation and change what's happening), use:

   ```bash
   npm run screenshots:wporg:ui
   ```


3. For _debug_ mode (which will open the browser along with Playwright Editor and allows you to record what's happening), use the following command:

   ```bash
   npm run screenshots:wporg:debug
   ```

   Run files that have *events.spec* in the file name.
   ```bash
   npm run screenshots:wporg:debug -- events.spec
   ```

   > [!NOTE]
   > When writing a screenshot-generator(-test), using the debug mode is recommended since it will allow you to see the browser and the test in action.

4. Run Tests independently _AND_ visually using the [Playwright VSCode extension](https://playwright.dev/docs/getting-started-vscode)

    ...

### Ressources

#### Playwright & WordPress

- [Playwright Screenshots API](https://playwright.dev/docs/screenshots)
- [End-To-End Playwright test utils for WordPress](https://github.com/WordPress/gutenberg/blob/trunk/packages/e2e-test-utils-playwright/README.md)

#### Screenshots for WordPress Plugins

- 

#### More about `wp-playground/cli`, as the environment

Examples with great documentation:

- [Playground CLI · WordPress/wordpress-playground#1289](https://github.com/WordPress/wordpress-playground/pull/1289)
- [PoC: Run E2E tests with WP Playground · WordPress/gutenberg#62692](https://github.com/WordPress/gutenberg/pull/62692)
- [Use WordPress Playground · swissspidy/wp-performance-action#173](https://github.com/swissspidy/wp-performance-action/pull/173)
