#!/bin/bash

# Configure Composer to allow contrib recipes
docker exec -it phpCOSY composer config extra.symfony.allow-contrib true

# Install Composer dependencies
docker exec -it phpCOSY composer install

# Run Yarn
docker exec -it nodeCOSY yarn

# Run Yarn encore dev
docker exec -it nodeCOSY yarn encore dev

# Run Yarn encore dev --watch
docker exec -it nodeCOSY yarn encore dev --watch &

# Run Yarn add aos
docker exec -it nodeCOSY yarn add aos

# Install additional PHP dependencies
docker exec -it phpCOSY composer require norkunas/youtube-dl-php
docker exec -it phpCOSY apk add --no-cache youtube-dl ffmpeg
docker exec -it phpCOSY composer update

# Require symfony/notifier
yes | docker exec -it phpCOSY composer require symfony/notifier

# Require fakerphp/faker --dev
yes | docker exec -it phpCOSY composer require fakerphp/faker --dev

# Set permissions and ownership for converted_files directory
echo "Setting permissions and ownership for converted_files directory..."
docker exec -it phpCOSY /bin/sh -c "chmod -R 775 /var/www/html/public/converted_files && chown -R www-data:www-data /var/www/html/public/converted_files"

echo "All commands executed successfully!"





