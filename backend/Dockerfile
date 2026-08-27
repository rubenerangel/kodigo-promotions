FROM php:8.4-cli-alpine

# Runtime tools and build dependencies for the required PHP extensions.
RUN apk add --no-cache bash curl git unzip icu-libs libzip libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql bcmath intl zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependency layer first so application edits do not invalidate the vendor cache.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-dev --prefer-dist --optimize-autoloader

COPY . .

# Laravel writable paths. They are excluded by .dockerignore, so recreate them
# before dumping the autoloader: package:discover writes to bootstrap/cache.
RUN mkdir -p storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN composer dump-autoload --optimize --no-dev --no-interaction

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
# Normalize CRLF introduced by Windows checkouts; otherwise the shebang breaks
# with "exec format error".
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
