# PHP 8.2 + Apache for Render
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo_mysql  && a2enmod rewrite headers  && sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf  && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html/
COPY .render/apache-security.conf /etc/apache2/conf-available/spirited-security.conf
RUN a2enconf spirited-security  && mkdir -p /var/www/html/images /var/www/html/assets/images  && chown -R www-data:www-data /var/www/html/images /var/www/html/assets/images

# Catch PHP parse errors during the build, before a broken version goes live.
RUN find /var/www/html -type f -name '*.php' -print0 | xargs -0 -n1 php -l

EXPOSE 10000
CMD ["apache2-foreground"]
