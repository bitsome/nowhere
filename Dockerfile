FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor sqlite-libs sqlite-dev \r
    libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev \r
    libxml2-dev zip unzip curl \r
    && docker-php-ext-configure gd --with-freetype --with-jpeg \r
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_sqlite mbstring exif bcmath gd opcache \r
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader 2>/dev/null || true

COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache
RUN mkdir -p /run/nginx database

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini 2>/dev/null || true

COPY docker/startup.sh /startup.sh
RUN chmod +x /startup.sh

EXPOSE 8080
CMD ["/startup.sh"]