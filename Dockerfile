FROM php:8.4-alpine

RUN apk add --no-cache postgresql-dev bash

RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pgsql pdo_pgsql

WORKDIR /app
