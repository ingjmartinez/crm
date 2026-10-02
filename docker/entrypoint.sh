#!/bin/sh
set -eu

mkdir -p /var/www/public/build
for asset in /opt/crm-build/*; do
    if [ "${asset##*/}" != manifest.json ]; then
        cp -a "$asset" /var/www/public/build/
    fi
done
cp /opt/crm-build/manifest.json /var/www/public/build/.manifest.json.tmp
mv -f /var/www/public/build/.manifest.json.tmp /var/www/public/build/manifest.json

exec "$@"
