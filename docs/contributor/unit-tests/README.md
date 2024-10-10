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

## Resources

### PMC Unit Test Framework

GatherPress uses the *PMC Unit Test Framework* because it:

> [...] provide[s] common utilities and data mocking for unit tests in a WordPress environment.

> This plugin was originally written for internal use at Penske Media [...] Our hope is that other teams find this plugin as useful as we do when writing unit tests in WordPress.
>
> <https://github.com/penske-media-corp/pmc-unit-test>

- [Installation](https://github.com/penske-media-corp/pmc-unit-test/tree/main?tab=readme-ov-file#installation)
- [Usage](https://github.com/penske-media-corp/pmc-unit-test/tree/main?tab=readme-ov-file#usage)
- [Data Mocking Overview](https://github.com/penske-media-corp/pmc-unit-test/blob/main/src/mocks/README.md)
    - [$this->mock->http()](https://github.com/penske-media-corp/pmc-unit-test/blob/main/docs/mock-http.md)
    - [$this->mock->input()](https://github.com/penske-media-corp/pmc-unit-test/blob/main/docs/mock-input.md)
    - [$this->mock->mail()](https://github.com/penske-media-corp/pmc-unit-test/blob/main/docs/mock-mail.md)
    - [$this->mock->post()](https://github.com/penske-media-corp/pmc-unit-test/blob/main/docs/mock-post.md)
    - [$this->mock->post()->is_amp()](https://github.com/penske-media-corp/pmc-unit-test/blob/main/docs/mock-post.md)
    - [$this->mock->user()](https://github.com/penske-media-corp/pmc-unit-test/blob/main/docs/mock-user.md)
    - [$this->mock->wp()](https://github.com/penske-media-corp/pmc-unit-test/blob/main/docs/mock-wp.md)
