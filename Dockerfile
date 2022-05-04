FROM php:8.1

ADD https://github.com/ufoscout/docker-compose-wait/releases/download/2.9.0/wait /usr/local/bin/docker-compose-wait
RUN chmod +x  /usr/local/bin/docker-compose-wait
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions mbstring bcmath pcntl intl sockets curl zip

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer
COPY . /opt/app/
WORKDIR /opt/app/

ENTRYPOINT ["php","./entry-point.php"]
