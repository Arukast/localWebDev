FROM php:8.3-fpm

# Install system dependencies & PHP extensions using the official extension installer helper
RUN curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd zip intl mysqli pdo_mysql pdo_pgsql pgsql opcache redis pcntl bcmath exif soap xdebug


# Install Node.js & npm (copied from official Node image) + git, unzip
COPY --from=node:lts-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:lts-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm && \
    apt-get update && apt-get install -y git unzip && rm -rf /var/lib/apt/lists/*

# Install Composer permanently
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Copy custom php.ini configurations
COPY php.ini /usr/local/etc/php/conf.d/custom-php.ini

WORKDIR /var/www/html
