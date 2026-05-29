FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql mysqli
COPY src/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html
