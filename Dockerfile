FROM php:8.4-fpm
RUN apt-get update && apt-get install -y \
    git curl zip unzip supervisor \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring gd zip bcmath pcntl

# pcntl WAJIB untuk queue worker: tanpa itu `php artisan queue:work` tidak bisa
# menegakkan --timeout / $job->timeout (pakai pcntl_alarm) maupun menangani
# sinyal restart. Efeknya satu job yang menggantung (mis. panggilan HTTP ke
# provider AI yang tidak kunjung balas) membekukan SATU-SATUNYA worker
# selamanya -- seluruh antrean berhenti. Lihat docker/supervisord.conf.

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN echo "upload_max_filesize=25M\npost_max_size=30M" > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/ecosystem

COPY . .

RUN git config --global --add safe.directory /var/www/ecosystem
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || composer install --no-interaction --prefer-source --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# php-fpm DAN queue worker Laravel (php artisan queue:work) dijalankan
# bersama di container ini lewat supervisord -- lihat docker/supervisord.conf.
# Tanpa ini job yang di-queue (mis. Word Report Generator) tidak pernah
# diproses di production karena tidak ada yang menjalankan queue:work.
# Deploy tetap sama seperti sebelumnya (rebuild + restart container ini),
# tidak ada container/langkah server tambahan.
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]