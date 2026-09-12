FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/addtomar.ini
COPY docker/start.sh /usr/local/bin/start-addtomar.sh

WORKDIR /var/www/html
COPY . /var/www/html

RUN chmod +x /usr/local/bin/start-addtomar.sh \
    && mkdir -p /var/www/html/data/uploads/receipts \
        /var/www/html/data/uploads/orders \
        /var/www/html/data/uploads/medicines \
        /var/www/html/data/uploads/pharmacies \
    && chown -R www-data:www-data /var/www/html

EXPOSE 10000
CMD ["start-addtomar.sh"]
