FROM php:8.2-cli-alpine

WORKDIR /var/www/html

RUN docker-php-ext-install pdo pdo_mysql

COPY composer.json ./
# RUN composer install --no-dev --optimize-autoloader || true

COPY src/ ./src/

EXPOSE 8082

CMD ["php", "-S", "0.0.0.0:8082", "-t", "src", "src/index.php"]
