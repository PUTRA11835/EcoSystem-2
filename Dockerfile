FROM php:8.2-cli

# Install sistem dependensi
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    xml \
    ctype \
    json \
    zip \
    gd

# Install Node.js untuk Vite
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
    && apt-get install -y nodejs

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy aplikasi
COPY . .

# Set permission
RUN chmod -R 775 storage bootstrap/cache

# Install dependensi PHP
RUN composer install --optimize-autoloader --no-dev --no-scripts --no-interaction

# Build frontend (pastikan package-lock.json ada!)
RUN npm ci && npm run build

# Jalankan server (tanpa generate key di sini — set via Railway)
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]