FROM php:8.4-fpm
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring gd zip bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/ecosystem

COPY . .

RUN git config --global --add safe.directory /var/www/ecosystem
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || composer install --no-interaction --prefer-source --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache