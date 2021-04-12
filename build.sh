#!/bin/bash
ulimit -n 100000;
composer update --no-dev;
php ./lib/bin/phing release;
composer update;