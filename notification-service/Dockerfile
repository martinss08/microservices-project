
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y     git unzip libzip-dev librabbitmq-dev     && docker-php-ext-install pdo pdo_mysql zip bcmath

RUN pecl install redis     && docker-php-ext-enable redis

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
