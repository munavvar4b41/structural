FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    $PHPIZE_DEPS \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    supervisor \
    cron \
    nano \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    zip \
    bcmath \
    intl \
    gd \
    pcntl \
    exif

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=docker.io/library/composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]