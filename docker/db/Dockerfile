FROM ubuntu:16.04

MAINTAINER lxerxa <lxerxa@126.com>

RUN apt-get update && \
    apt-get -yq install \
        netcat-openbsd\
        mongodb

RUN touch /.initdb

ADD dbdata /dbdata

ADD scripts /scripts
RUN chmod a+x /scripts/*.sh

EXPOSE 27017

VOLUME ["/data"]

CMD ["/scripts/run.sh"]
