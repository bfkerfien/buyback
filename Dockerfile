FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer update --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

FROM php:8.3-fpm-alpine
WORKDIR /var/www/html
RUN apk add --no-cache icu-libs libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install intl mbstring opcache pdo_mysql zip \
    && apk del .build-deps
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache
RUN php artisan package:discover --ansi
COPY docker/php/zz-buyback.ini /usr/local/etc/php/conf.d/zz-buyback.ini
EXPOSE 9000
CMD ["php-fpm"]
