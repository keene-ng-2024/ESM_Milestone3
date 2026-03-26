FROM moodlehq/moodle-php-apache:8.3

# Install Moodle 4.5 (latest stable)
ARG MOODLE_VERSION=MOODLE_405_STABLE

RUN apt-get update && apt-get install -y --no-install-recommends git \
    && git clone --branch ${MOODLE_VERSION} --depth 1 \
       https://github.com/moodle/moodle.git /tmp/moodle \
    && cp -r /tmp/moodle/. /var/www/html/ \
    && rm -rf /tmp/moodle \
    && chown -R www-data:www-data /var/www/html \
    && apt-get purge -y git && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# Create moodledata directory
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata \
    && chmod 775 /var/www/moodledata

# Set Apache document root (required by moodlehq base image)
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Copy entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
