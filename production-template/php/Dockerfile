FROM php:8.3-fpm-alpine

# Copy Composer 2 binary from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Use Alpine for the smallest production footprint (~100MB instead of 400MB)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk add --no-cache fcgi && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd zip intl mysqli pdo_mysql pdo_pgsql pgsql opcache redis pcntl

# Copy custom php.ini production configurations
COPY php.ini /usr/local/etc/php/conf.d/custom-php.ini

WORKDIR /var/www/html

# Ensure www-data ownership
RUN chown -R www-data:www-data /var/www/html

USER www-data


