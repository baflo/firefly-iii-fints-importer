FROM php:8.3-fpm-alpine

WORKDIR /
RUN apk add composer git

COPY . .

RUN rm -f /data/configurations/*

RUN composer install --no-dev && composer clearcache

EXPOSE 8080

ENTRYPOINT [ "php", "-S", "0.0.0.0:8080", "/app/index.php" ]  
