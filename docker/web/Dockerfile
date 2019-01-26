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
        php-dom\
        php-zip\
        php-ldap\
        php-mongodb\
        cron

RUN a2enmod rewrite

WORKDIR /var/www/
RUN git clone https://github.com/lxerxa/actionview.git actionview

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR actionview
RUN cp composer.phar /usr/bin/composer && composer install --no-dev

RUN /bin/bash config.sh

ADD conf/env.ini ./.env 

ADD conf/000-default.conf /etc/apache2/sites-available/000-default.conf 

ADD conf/crontabfile /var/www/actionview/crontabfile 

ADD scripts /scripts
RUN chmod a+x /scripts/*.sh

VOLUME ["/var/www/actionview/storage", "/var/log/apache2"]  

CMD ["/scripts/run.sh"]
