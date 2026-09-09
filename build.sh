#!/bin/bash
ulimit -n 100000;

# phing reads build-config.yaml via a property file loader that pulls in
# symfony/yaml — but symfony/yaml is composer-"replace"d (provided by the
# PrestaShop 9 host at runtime, not vendored), so it won't exist in lib/ once
# --no-dev deps are installed. Convert to .properties for phing instead.
sed 's/: /=/' build-config.yaml > build-config.properties

composer update --no-dev;
php ./lib/bin/phing release;
rm -f build-config.properties
composer update;