FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql pdo_pgsql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-dev --prefer-dist --optimize-autoloader

# Copy application code
COPY . .

# Create required directories and set permissions before post-install scripts
RUN mkdir -p bootstrap/cache storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    && chmod -R 775 bootstrap/cache storage \
    && chown -R www-data:www-data bootstrap/cache storage

# Run post-install scripts
RUN composer dump-autoload --optimize

EXPOSE 8000
