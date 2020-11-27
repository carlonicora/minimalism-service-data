FROM php:7.4-fpm-alpine as base

RUN apk update \
    && apk add --no-cache curl g++ make autoconf bash

RUN docker-php-ext-install mysqli bcmath \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && docker-php-source extract \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-source delete \
    && addgroup -g 1000 -S developers \
    && adduser -u 1000 -S developer -G developers

COPY ./php-cli/configurations/* /usr/local/etc/php/conf.d/