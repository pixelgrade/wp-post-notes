# WP Post Notes

WordPress library for adding public and private notes to posts, pages, or any custom post type.

## About

WordPress library for adding public and private notes to posts, pages, or any custom post type.

## Releases

Before creating a new release zip, run the `composer run prepare-for-release` command to keep everything production-oriented.

## Running Tests

To run the PHPUnit tests, in the root directory of the plugin, run something like:

```
./vendor/bin/phpunit --testsuite=Unit --colors=always
```
or
```
composer run tests
```

Bear in mind that there are **simple unit tests** (hence the `--testsuite=Unit` parameter) that are very fast to run, and there are **integration tests** (`--testsuite=Integration`) that need to load the entire WordPress codebase, recreate the db, etc. Choose which ones you want to run depending on what you are after.

You can run either the unit tests or the integration tests with the following commands:

```
composer run tests-unit
```
or
```
composer run tests-integration
```

**Important:** Before you can run the tests, you need to create a `.env` file in `tests/phpunit/` with the necessary data. You can copy the already existing `.env.example` file. Further instructions are in the `.env.example` file.

## Credits

This WordPress library uses much code/logic extracted and modified from [WooCommerce](https://github.com/woocommerce/woocommerce), mainly the order notes logic.
