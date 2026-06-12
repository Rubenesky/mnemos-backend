FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP upload limits
RUN echo "upload_max_filesize=50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy only composer manifests first (better layer caching)
COPY composer.json composer.lock ./

# Create required directories BEFORE composer runs so it can write to bootstrap/cache
RUN mkdir -p bootstrap/cache storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    && chmod -R 777 bootstrap/cache storage

# Install PHP dependencies
RUN composer install --no-scripts --no-dev --prefer-dist --optimize-autoloader

# Copy full application code
COPY . .

# Re-apply permissions after COPY (COPY resets ownership to root)
RUN chmod -R 777 bootstrap/cache storage \
    && chown -R www-data:www-data bootstrap/cache storage

# Run post-install scripts (bootstrap/cache guaranteed to exist and be writable)
RUN composer dump-autoload --optimize

EXPOSE 10000

# Run migrations and seed demo account at container startup
CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --class=DemoUserSeeder --force && php -S 0.0.0.0:10000 -t public public/router.php"]
