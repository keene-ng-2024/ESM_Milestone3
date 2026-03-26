FROM php:8.1-apache

# Install Moodle dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libicu-dev \
        libxml2-dev libldap2-dev libssl-dev unzip git cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd mysqli intl zip soap opcache xmlrpc exif \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# PHP settings for Moodle
RUN { \
    echo 'max_input_vars = 5000'; \
    echo 'upload_max_filesize = 256M'; \
    echo 'post_max_size = 256M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 300'; \
} > /usr/local/etc/php/conf.d/moodle.ini

# Download Moodle 4.3 (stable, compatible with PHP 8.1)
RUN git clone --depth=1 --branch MOODLE_403_STABLE \
        https://github.com/moodle/moodle.git /var/www/html \
    && chown -R www-data:www-data /var/www/html

# Create moodledata directory
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata

# Entrypoint script to generate config.php from env vars
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
