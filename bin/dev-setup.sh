#!/bin/sh
# Local development seed. Runs inside the docker-compose "cli" service.
# Local-only test login: admin / admin (never use outside this Docker setup).
set -e

if ! wp core is-installed 2>/dev/null; then
    wp core install --url=http://localhost:8080 --title="UnlimitedSchema Dev" \
        --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
fi

wp rewrite structure '/%postname%/' --hard
wp plugin activate unlimited-schema

if ! wp post list --name=schema-demo --format=ids | grep -q .; then
    wp post create --post_title="Schema Demo" --post_name=schema-demo --post_status=publish --post_author=1 \
        --post_excerpt="A demo post for UnlimitedSchema." --post_content="Hello from UnlimitedSchema."
fi

echo "Ready: http://localhost:8080/schema-demo/  (admin / admin)"
