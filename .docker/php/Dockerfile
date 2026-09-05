FROM composer:2 AS composer

FROM php:8.5-fpm-alpine

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache curl-dev git openssh-client \
    && docker-php-ext-install curl \
    && git config --system url."git@github.com:".insteadOf "https://github.com/" \
    && git config --system --add safe.directory /var/www/html

COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY .docker/php/errors.ini /usr/local/etc/php/conf.d/errors.ini
COPY --chmod=755 .docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
