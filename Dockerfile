FROM php:8.3-fpm-alpine

RUN apk add composer git

COPY . /app/

RUN rm -f /data/configurations/*

RUN cd /app && composer install --no-dev && composer clearcache

EXPOSE 8080

ENTRYPOINT [ "php", "-S", "0.0.0.0:8080", "/app/index.php" ]  
