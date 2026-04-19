FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libonig-dev \
    && docker-php-ext-install intl pdo_mysql zip opcache bcmath \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN printf '%s\n' \
    'opcache.enable=1' \
    'opcache.enable_cli=1' \
    'opcache.validate_timestamps=0' \
    'opcache.revalidate_freq=0' \
    'opcache.max_accelerated_files=20000' \
    'opcache.memory_consumption=128' \
    'realpath_cache_size=4096K' \
    'realpath_cache_ttl=600' \
    > /usr/local/etc/php/conf.d/opcache-performance.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

RUN printf '<VirtualHost *:80>\n    DocumentRoot /var/www/html/public\n    <Directory /var/www/html/public>\n        AllowOverride All\n        Require all granted\n    </Directory>\n</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf
