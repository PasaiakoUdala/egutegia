FROM node:6.17-alpine AS node
FROM php:7.3-fpm-alpine3.15


COPY --from=node /usr/lib /usr/lib
COPY --from=node /usr/local/share /usr/local/share
COPY --from=node /usr/local/lib /usr/local/lib
COPY --from=node /usr/local/include /usr/local/include
COPY --from=node /usr/local/bin /usr/local/bin

ARG DOCKER_TIMEZONE="Europe/Madrid"
ARG DEBUGMODE=0
ARG FOSJSDUMP=0

ENV PS1="\u@\h:\w\\$ "

RUN echo "${DOCKER_TIMEZONE}" > /etc/timezone

# 2 Set working directory
WORKDIR /usr/src/app

# 3 Install Additional dependencies
RUN apk update && apk add --no-cache \
    build-base shadow vim curl bash \
    zip libzip-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    freetype-dev \
    postgresql-dev \
    aspell-dev \
    git\
    autoconf acl

# 4 Add and Enable PHP-PDO Extenstions
RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN docker-php-ext-enable pdo_mysql opcache
RUN docker-php-ext-install pdo_pgsql
RUN docker-php-ext-install pgsql
RUN docker-php-ext-install pspell
RUN docker-php-ext-enable pdo_pgsql pgsql pspell
RUN docker-php-ext-install zip
#RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype
RUN docker-php-ext-install gd
RUN docker-php-ext-configure intl && docker-php-ext-install intl

# ldap
RUN apk add --update --no-cache \
          libldap && \
      # Build dependancy for ldap \
      apk add --update --no-cache --virtual .docker-php-ldap-dependancies \
          openldap-dev && \
      docker-php-ext-configure ldap && \
      docker-php-ext-install ldap && \
      apk del .docker-php-ldap-dependancies && \
      php -m;

RUN pecl install -o -f redis apcu  \
&&  rm -rf /tmp/pear \
&&  docker-php-ext-enable redis apcu

RUN apk --no-cache add nodejs yarn npm --repository=http://dl-cdn.alpinelinux.org/alpine/edge/community


# 5 Install PHP Composer
# ADI!!! 2.2.6 izan behar du, produkzioan bezala
#RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# copy the Composer PHAR from the Composer image into the PHP image
COPY --from=composer:2.2.6 /usr/bin/composer /usr/local/bin/composer

RUN npm install
RUN npm install -g gulp
RUN npm install gulp
ENV PATH="/usr/src/app/node_modules/.bin:${PATH}"

# 6 Remove Cache
RUN rm -rf /var/cache/apk/*

WORKDIR /usr/src/app

RUN mkdir -p var/cache/prod var/cache/dev var/cache/test var/log \
   && chown -R www-data:www-data var/
#   && chmod -R ug+rwX var/ \

# 7 Add UID '1000' to www-data
#RUN usermod -u ${USER_ID} www-data

#RUN setfacl -dR -m u:"www-data":rwX -m u:$(whoami):rwX var \
#    && setfacl -R -m u:"www-data":rwX -m u:$(whoami):rwX var

# 8 Copy existing application directory permissions
RUN rm -fr node_modules
COPY --chown=www-data:www-data . /usr/src/app

RUN composer install --no-scripts --prefer-dist --no-interaction --optimize-autoloader

#RUN php bin/console cache:clear

RUN mkdir -p /usr/src/app/web/uploads
RUN chmod -R 777 /usr/src/app/web/uploads

# 9 Change current user
USER www-data
# 10 Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]

