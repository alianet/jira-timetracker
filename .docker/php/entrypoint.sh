#!/bin/sh

set -eu

log_directory=/var/www/html/var/log

mkdir -p "$log_directory"
chgrp www-data "$log_directory"
chmod 0775 "$log_directory"

find "$log_directory" -maxdepth 1 -type f -name '*.log' -exec chgrp www-data {} + -exec chmod g+w {} +

exec docker-php-entrypoint "$@"
