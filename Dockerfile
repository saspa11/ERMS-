# Use the official PHP image with Apache web server
FROM php:8.2-apache

# Install required system packages and extension tools
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install necessary PHP extensions for databases and encryption
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql

# Enable Apache rewrite module (important for clean routing)
RUN a2enmod rewrite

# Copy all your project files into the Apache web directory
COPY . /var/www/html/

# Set correct permissions so Apache can access your application
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for web traffic
EXPOSE 80
