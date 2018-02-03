#!/bin/bash

USER=${MONGODB_USERNAME:-actionview}
PASS=${MONGODB_PASSWORD:-secret}
DB=${MONGODB_DBNAME:-actionviewdb}

/usr/bin/mongod --dbpath /data --nojournal  &
while ! nc -vz localhost 27017; do sleep 1; done

echo "Creating user: \"$USER\"..."
mongo $DB --eval "db.createUser({ user: '$USER', pwd: '$PASS', roles: [ { role: 'readWrite', db: '$DB' } ] });"

echo "Initializing data..."
mongorestore -h localhost -u $USER -p $PASS -d $DB --drop /dbdata

/usr/bin/mongod --dbpath /data --shutdown

echo "========================================================================"
echo "MongoDB User: \"$USER\""
echo "MongoDB Password: \"$PASS\""
echo "MongoDB Database: \"$DB\""
echo "========================================================================"

rm -f /.initdb
