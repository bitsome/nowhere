FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor sqlite-libs sqlite-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev libxml2-dev zip unzip curl && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j$(nproc) pdo pdo_mysql pdo_sqlite mbstring exif bcmath gd opcache && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache && mkdir -p /run/nginx database && touch database/database.sqlite && chown www-data:www-data database/database.sqlite

# nginx
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# supervisor
RUN echo '[supervisord]' > /etc/supervisor/supervisord.conf && echo 'nodaemon=true' >> /etc/supervisor/supervisord.conf && echo '[program:php-fpm]' >> /etc/supervisor/supervisord.conf && echo 'command=php-fpm' >> /etc/supervisor/supervisord.conf && echo 'autostart=true' >> /etc/supervisor/supervisord.conf && echo 'autorestart=true' >> /etc/supervisor/supervisord.conf && echo '[program:nginx]' >> /etc/supervisor/supervisord.conf && echo 'command=nginx -g \"daemon off;\"' >> /etc/supervisor/supervisord.conf && echo 'autostart=true' >> /etc/supervisor/supervisord.conf

# entrypoint (shell script inline)
RUN echo '#!/bin/sh' > /entry.sh && echo 'php artisan key:generate --show | head -1' >> /entry.sh && echo 'php artisan migrate --force --no-interaction || true' >> /entry.sh && echo 'php artisan config:cache 2>/dev/null || true' >> /entry.sh && echo 'exec supervisord -c /etc/supervisor/supervisord.conf' >> /entry.sh && chmod +x /entry.sh

EXPOSE 8080
CMD ["/entry.sh"]
