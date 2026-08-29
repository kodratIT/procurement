FROM php:8.4-cli
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS libpq-dev libicu-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-mark auto $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN rm -rf vendor \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
COPY . .
RUN rm -rf bootstrap/cache/*.php \
    && composer dump-autoload --no-dev --optimize --no-scripts
EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && php artisan optimize && php artisan serve --host=0.0.0.0 --port=8000"]
