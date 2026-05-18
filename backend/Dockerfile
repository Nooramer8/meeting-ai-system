FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev icu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql intl zip

WORKDIR /app

COPY --from=vendor /app /app
COPY docker/start.sh /usr/local/bin/start-meeting-ai
RUN chmod +x /usr/local/bin/start-meeting-ai \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["start-meeting-ai"]
