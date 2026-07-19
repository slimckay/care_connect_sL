FROM php:8.2-apache

# MySQL + OPcache (faster PHP after container starts)
RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers expires

# OPcache tuned for small free-tier instances
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=64'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'realpath_cache_size=4096K'; \
    echo 'realpath_cache_ttl=600'; \
} > /usr/local/etc/php/conf.d/opcache-render.ini

# Short timeouts so a slow DB does not hang the whole request forever
RUN { \
    echo 'max_execution_time=30'; \
    echo 'default_socket_timeout=10'; \
    echo 'display_errors=0'; \
    echo 'log_errors=1'; \
} > /usr/local/etc/php/conf.d/render.ini

WORKDIR /var/www/html
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
