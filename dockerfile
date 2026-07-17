FROM php:8.2-apache

# Install MySQL extension
RUN docker-php-ext-install pdo_mysql

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy all files to the container
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80
