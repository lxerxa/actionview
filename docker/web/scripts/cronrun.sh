#!/bin/bash

/etc/init.d/cron start
crontab /var/www/actionview/crontabfile

touch /var/log/cron.log
tail -f /var/log/cron.log
