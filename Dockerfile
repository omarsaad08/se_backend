FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
  git \
  curl \
  libpng-dev \
  libxml2-dev \
  zip \
  unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY src/ .

# Create user and set permissions
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www
RUN chown -R www:www /var/www/html

USER www

EXPOSE 9000