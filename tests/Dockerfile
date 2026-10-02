# Test runner: the official WordPress image (PHP 8.2 + mysqli + core in
# /usr/src/wordpress) plus Composer.
ARG WP_IMAGE=wordpress:php8.2-apache
FROM ${WP_IMAGE}
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Optional: older images sit on archived Debian releases where apt no longer works.
RUN (apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*) \
    || echo "apt unavailable; Composer will use PHP's zip extension"
ENTRYPOINT []
CMD ["composer", "test"]
