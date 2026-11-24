FROM php:8.2-cli-alpine
RUN apk add --no-cache oniguruma-dev libxml2-dev unzip \
    && docker-php-ext-install pdo_mysql mbstring bcmath dom xml xmlwriter
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chmod -R 775 storage bootstrap/cache
EXPOSE 8082
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8082"]
