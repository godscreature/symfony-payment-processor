FROM php:8.5-fpm-trixie

ARG UID=1000
ARG GID=1000

# Composer and the PHP extension installer come from their official images.
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions \
        intl \
        pdo_pgsql \
        zip \
        amqp \
        redis \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY conf.d/app.ini $PHP_INI_DIR/conf.d/zz-app.ini

# Run as the host user, so files created in the container belong to you.
RUN groupadd --gid ${GID} app \
    && useradd --uid ${UID} --gid ${GID} --create-home --shell /bin/bash app \
    && mkdir -p /var/www/html /home/app/.composer/cache \
    && chown -R app:app /var/www/html /home/app/.composer

ENV COMPOSER_HOME=/home/app/.composer

WORKDIR /var/www/html
USER app

CMD ["php-fpm"]
