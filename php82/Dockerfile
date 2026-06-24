FROM php:8.2-fpm

# Install system dependencies & PHP extensions using the official extension installer helper
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd zip intl mysqli pdo_mysql pdo_pgsql pgsql opcache redis

# Copy custom php.ini configurations
COPY php.ini /usr/local/etc/php/conf.d/custom-php.ini

WORKDIR /var/www/html
