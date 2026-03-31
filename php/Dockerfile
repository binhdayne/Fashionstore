FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev libicu-dev libxslt-dev libonig-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    curl git unzip zip fonts-freefont-ttf \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
       pdo_mysql zip intl xsl soap bcmath opcache gd sockets \
    && echo "memory_limit=2G" > /usr/local/etc/php/conf.d/memory.ini

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
