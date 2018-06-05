#!/bin/bash

/etc/init.d/cron start
crontab /var/www/actionview/crontabfile

source /etc/apache2/envvars && exec apache2 -D FOREGROUND
