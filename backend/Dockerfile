# API Laravel del ICAP (capa 2) para ejecutar todo con Docker en desarrollo.
# Se usa desde la raíz:  docker compose --profile app up -d --build
# composer.lock exige PHP 8.4 (Symfony 8)
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd zip intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencias primero: esta capa solo se reconstruye si cambia composer.lock
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize \
    && sed -i 's/\r$//' docker-entrypoint.sh \
    && chmod +x docker-entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["./docker-entrypoint.sh"]
# Se usa el servidor de PHP directamente (no "artisan serve") porque artisan serve
# no pasa a sus procesos las variables de entorno del contenedor (DB_HOST, DB_PORT).
ENV PHP_CLI_SERVER_WORKERS=4
CMD ["sh", "-c", "cd public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
