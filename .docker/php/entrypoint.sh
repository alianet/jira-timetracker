#!/bin/sh

set -eu

log_directory=/var/www/html/var/log
container_cache_directory=/var/www/html/var/cache/container

mkdir -p "$log_directory" "$container_cache_directory"
chgrp www-data "$log_directory" "$container_cache_directory"
chmod 0775 "$log_directory" "$container_cache_directory"

find "$log_directory" -maxdepth 1 -type f -name '*.log' -exec chgrp www-data {} + -exec chmod g+w {} +
find "$container_cache_directory" -maxdepth 1 -type f -exec chgrp www-data {} + -exec chmod g+w {} +

exec docker-php-entrypoint "$@"
