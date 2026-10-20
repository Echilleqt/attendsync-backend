FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip zip curl \
    python3 python3-pip python3-venv \
    libzip-dev libjpeg-dev libpng-dev libfreetype6-dev \
    libx11-6 libglib2.0-0 libsm6 libxext6 libxrender-dev \
    && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan storage:link || true

EXPOSE 10000

CMD php artisan serve --host=0.0.0.0 --port=10000