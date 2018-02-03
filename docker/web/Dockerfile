FROM ubuntu:16.04

MAINTAINER lxerxa <lxerxa@126.com>

RUN apt-get update && \
    apt-get -yq install \
        curl\
        git\
        apache2\
        make\
        zip\
        php7.0\
        libapache2-mod-php7.0\
        php-mbstring\
        php-gd\
        php-mcrypt\
        php-curl\
        php-mongodb

RUN a2enmod rewrite

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /var/www/
RUN git clone https://github.com/lxerxa/actionview.git actionview

WORKDIR actionview
RUN composer install --no-dev

RUN /bin/bash config.sh

ADD conf/env.ini ./.env 

ADD conf/000-default.conf /etc/apache2/sites-available/000-default.conf 

ADD scripts/run.sh /run.sh
RUN chmod a+x /run.sh

EXPOSE 80

VOLUME ["/var/www/actionview/storage", "/var/log/apache2"]  
 
CMD ["/run.sh"]
