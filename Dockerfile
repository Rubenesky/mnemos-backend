FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs
RUN npm install && npm run build

RUN cp .env.example .env && \
    php artisan key:generate && \
    sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=pgsql/' .env && \
    sed -i 's/DB_PORT=3306/DB_PORT=5432/' .env

RUN chown -R www-data:www-data storage bootstrap/cache

CMD php artisan config:clear && \
    php artisan storage:link && \
    php artisan migrate --force && \
    sed -i "s/Listen 80/Listen ${PORT:-10000}/" /etc/apache2/ports.conf && \
    sed -i "s/:80>/:${PORT:-10000}>/" /etc/apache2/sites-enabled/000-default.conf && \
    apache2-foreground