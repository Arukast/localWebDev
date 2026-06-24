FROM php:8.3-fpm-alpine

# Use Alpine for the smallest production footprint (~100MB instead of 400MB)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd zip intl mysqli pdo_mysql opcache

# Copy custom php.ini production configurations
COPY php.ini /usr/local/etc/php/conf.d/custom-php.ini

WORKDIR /var/www/html
