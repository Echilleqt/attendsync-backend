FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl \
    python3 python3-dev python3-venv \
    build-essential cmake \
    libzip-dev libjpeg-dev libpng-dev libfreetype6-dev \
    libx11-6 libglib2.0-0 libsm6 libxext6 libxrender-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV VIRTUAL_ENV=/opt/venv
ENV PATH="/opt/venv/bin:${PATH}"
ENV FACE_RECOGNITION_PYTHON_BINARY=/opt/venv/bin/python

RUN python3 -m venv /opt/venv

WORKDIR /var/www/html

# Layer-cache Python dependencies (avoids recompiling dlib on every app change)
COPY requirements.txt .
RUN python -m pip install --no-cache-dir --upgrade pip \
    && python -m pip install --no-cache-dir -r requirements.txt

# Layer-cache Composer dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy full application code
COPY . .

# Generate optimized autoloader (halts build on failure)
RUN composer dump-autoload --no-dev --optimize

# Establish storage symlink (safe if symlink already exists)
RUN php artisan storage:link || true

EXPOSE 10000

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
