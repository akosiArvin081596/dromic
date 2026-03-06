#!/bin/bash
cd /var/www/dromic-is

git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan optimize

pm2 restart dromic-queue
pm2 restart dromic-reverb

echo "Deployed successfully!"
