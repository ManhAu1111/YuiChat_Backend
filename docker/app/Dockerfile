FROM php:8.2-fpm

# Install system dependencies and required libraries
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    libssl-dev \
    pkg-config

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install standard PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install MongoDB extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the Supervisor configuration file
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy custom PHP configuration
COPY docker/app/php.ini /usr/local/etc/php/conf.d/custom.ini

# The application code will be mounted here in local development via docker-compose volume.
# For production, you would COPY the code and run composer install here.

# Expose PHP-FPM and Reverb ports
EXPOSE 9000
EXPOSE 8080

# Start Supervisor (which will run php-fpm, queue, and reverb)
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
