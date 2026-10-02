<?php
/**
 * WordPress test-suite config. Values come from the environment so the same
 * file works locally and in docker-compose (see docker-compose.yml).
 */

define('ABSPATH', getenv('WP_CORE_DIR') ?: '/var/www/html/');

define('DB_NAME', getenv('WP_DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('WP_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_DB_PASSWORD') ?: 'root');
define('DB_HOST', getenv('WP_DB_HOST') ?: 'db');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Mercury Schema Tests');
define('WP_PHP_BINARY', 'php');
define('WPLANG', '');
define('WP_DEBUG', true);
