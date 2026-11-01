#!/bin/sh
set -e

# First run on a fresh clone: create the .env file and install dependencies.
if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# The seeder skips itself when the database already has users.
php artisan migrate --force --seed

# php-fpm workers run as www-data and need to write to these.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
