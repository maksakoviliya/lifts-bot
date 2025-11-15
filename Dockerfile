FROM php:8.4-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    intl \
    zip \
    pdo \
    pdo_mysql \
    gd \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www

# Копирование composer файлов для кэширования зависимостей
COPY composer.json composer.lock ./

# Установка зависимостей
RUN composer install --optimize-autoloader --no-dev --no-interaction --prefer-dist --no-scripts

# Копирование остальных файлов проекта
COPY . .

# Выполнение post-install скриптов
RUN composer dump-autoload --optimize

# Права доступа
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Оптимизация для production
RUN php artisan optimize:clear || true

# Открытие порта
EXPOSE 8000

# Запуск приложения
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}