# Test runner: the official WordPress image (PHP 8.2 + mysqli + core in
# /usr/src/wordpress) plus Composer.
FROM wordpress:php8.2-apache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
ENTRYPOINT []
CMD ["composer", "test"]
