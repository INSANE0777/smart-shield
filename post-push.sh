#!/bin/sh

set -eu

php_cmd="/usr/bin/php82"

${php_cmd} /usr/local/bin/composer dump-autoload 
${php_cmd} /usr/local/bin/composer install --optimize-autoloader

# Ensure cache directories are writable before clearing/rebuilding caches.
sudo chown -R nullfake:nginx storage/ bootstrap/cache/
sudo chmod -R 775 storage/ bootstrap/cache/

${php_cmd} artisan migrate --force

# Clear all framework caches (compiled views included).
${php_cmd} artisan optimize:clear

# Extra safety: remove any partially-written compiled views (rare, but causes ParseError).
rm -f storage/framework/views/*.php || true

${php_cmd} artisan config:clear
${php_cmd} artisan cache:clear
${php_cmd} artisan route:clear
${php_cmd} artisan view:clear
${php_cmd} artisan view:cache

rm -rf bootstrap/cache/*

${php_cmd} artisan livewire:publish

chown -R nullfake:nginx *

systemctl restart php82-php-fpm
systemctl restart supervisord
