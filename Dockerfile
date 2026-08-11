FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-libs sqlite-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev libxml2-dev zip unzip curl && docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_sqlite mbstring exif bcmath gd opcache && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache && mkdir -p database && touch database/database.sqlite && chown www-data:www-data database/database.sqlite && php artisan key:generate --force && php artisan migrate --force --no-interaction

EXPOSE 80
CMD php -S 0.0.0.0:80 -t public
