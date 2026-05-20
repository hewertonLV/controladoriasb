FROM php:8.3-apache

ENV TZ=America/Sao_Paulo

RUN a2enmod rewrite headers \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        unzip \
        libzip-dev zlib1g-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip exif pcntl bcmath gd intl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint-app.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-app.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-app.sh"]
CMD ["apache2-foreground"]
