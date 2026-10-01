<?php
/**
 * Bootstrap for integration tests against the WordPress test suite.
 * WP_TESTS_DIR defaults to the wp-phpunit/wp-phpunit Composer package.
 */

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$testsDir = getenv('WP_TESTS_DIR') ?: $root . '/vendor/wp-phpunit/wp-phpunit';
if (!getenv('WP_PHPUNIT__TESTS_CONFIG')) {
    putenv('WP_PHPUNIT__TESTS_CONFIG=' . $root . '/tests/integration/wp-tests-config.php');
}

require_once $testsDir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function () use ($root) {
    require $root . '/unlimited-schema.php';
});

require $testsDir . '/includes/bootstrap.php';
