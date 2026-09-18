#!/bin/sh

set -eu

log_directory=/var/www/html/var/log
container_cache_directory=/var/www/html/var/cache/container
project_uid="$(stat -c '%u' /var/www/html/composer.lock)"
project_gid="$(stat -c '%g' /var/www/html/composer.lock)"
composer_home="/tmp/app-composer-${project_uid}"

mkdir -p "$log_directory" "$container_cache_directory"
chown -R "$project_uid:$project_gid" "$log_directory" "$container_cache_directory"
if [ -d /var/www/html/vendor ]; then
    chown -R "$project_uid:$project_gid" /var/www/html/vendor
fi

mkdir -p "$composer_home"
chown "$project_uid:$project_gid" "$composer_home"
HOME="$composer_home" COMPOSER_HOME="$composer_home" su-exec "$project_uid:$project_gid" \
    composer install --no-interaction --prefer-dist

chgrp www-data "$log_directory" "$container_cache_directory"
chmod 0775 "$log_directory" "$container_cache_directory"

find "$log_directory" -maxdepth 1 -type f -name '*.log' -exec chgrp www-data {} + -exec chmod g+w {} +
find "$container_cache_directory" -maxdepth 1 -type f -exec chgrp www-data {} + -exec chmod g+w {} +

exec docker-php-entrypoint "$@"
