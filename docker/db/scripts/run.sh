#!/bin/bash

if [[ ! -d "/data" ]]; then
  mkdir /data
fi

if [[ -e /.initdb ]]; then
  /scripts/initdb.sh
fi

/usr/bin/mongod --dbpath /data --smallfiles --auth $@
