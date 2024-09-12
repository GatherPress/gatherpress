# PHP Unit tests

GatherPress allows to run **automated & manual php unit tests**, while sharing the same, `wp-env` based, setup.

## Automated tests

Check the results of the [*phpunit-tests action workflow*](https://github.com/GatherPress/gatherpress/actions/workflows/phpunit-tests.yml) at `https://github.com/GatherPress/gatherpress/actions/workflows/phpunit-tests.yml`.

## Manual tests

The unittest-setup can also be used to manually run the test suite. In general, only a `wp-env` instance is needed.

### Install dependencies

To run the unit tests you will have to install the requirements using the following commands:

```bash
composer install
```

> [!NOTE]
> You also need to use Node.js 20 or later

Install the dependencies to create the Playground testing instance, using the following command:

```bash
npm ci --legacy-peer-deps
```

### Start the Environment

A call to `npm run test:unit:php` will automatically setup a `wp-env` powered WordPress instance, already prepared to mount GatherPress from the current directory.

So while there is no technical need to start `wp-env` manually on its own, you might want to do so for any reason. If the environment is already running, the unit tests will run against that existing instance. You might want to start it with this command:


```bash
npm run wp-env -- start
```

The testing website is reachable at `http://127.0.0.1:8889`, the user is `admin` and the password is `password`. 

### Run the unit tests

To run the full suite with all unit tests, use the command:

```bash
npm run test:unit:php
```

To run only specific tests, you can call `npm run test:unit:php`:

- with the `--filter` argument,
    to execute all tests in `test/unit/php/includes/core/classes/class-test-event-query.php` for example:

    ```bash
    npm run test:unit:php -- --filter 'Test_Event_Query'
    ```

- with the `--group` argument, to execute all tests which share the same `@group` declaration, for example all tests related to `/ical` and `/feed/ical` endpoints of events & venues:

    ```bash
    npm run test:unit:php -- --group endpoints
    ```

- or with any other of [phpunit's command-line options](https://docs.phpunit.de/en/10.5/textui.html#command-line-options).