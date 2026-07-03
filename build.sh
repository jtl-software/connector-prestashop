#!/bin/bash
ulimit -n 100000;

# Convert build-config.yaml to .properties format for Phing
sed 's/: /=/' build-config.yaml > build-config.properties

composer update --no-dev;
php ./lib/bin/phing release;
rm -f build-config.properties
composer update;
