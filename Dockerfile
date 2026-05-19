FROM php:8.3-apache

ENV TZ=America/Sao_Paulo

RUN a2enmod rewrite headers \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev zlib1g-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip exif pcntl bcmath gd intl opcache \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
