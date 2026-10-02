#!/bin/sh
# Runs inside the "tests" container. Matches the WordPress test library to the
# image's WordPress core, then runs unit and integration tests.
#
#   WP_IMAGE=wordpress:6.0-php8.0-apache docker compose build tests
#   docker compose run --rm tests sh bin/test-all.sh
set -e

VER=$(php -r 'include "/usr/src/wordpress/wp-includes/version.php"; echo preg_replace("/^(\d+\.\d+).*/", "\$1", $wp_version);')
echo "== WordPress $VER on PHP $(php -r 'echo PHP_VERSION;')"

mkdir -p /tmp/wpp
cd /tmp/wpp
[ -f composer.json ] || echo '{}' > composer.json
composer require --quiet --no-interaction "wp-phpunit/wp-phpunit:$VER.*"

cd /plugin
export WP_TESTS_DIR=/tmp/wpp/vendor/wp-phpunit/wp-phpunit
vendor/bin/phpunit
vendor/bin/phpunit -c phpunit-integration.xml.dist
