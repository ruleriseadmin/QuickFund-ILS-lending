#!/usr/bin/env bash

# Change the permission for the storage folder to allow logging
sudo chmod -R 777 storage

# Change the permission for the vendor folder to allow laravel sail to be used
sudo chmod -R 777 vendor

# Kill the running containers
./vendor/bin/sail -f docker-compose.staging.yml down --remove-orphans

# Run the production script to set up production mode
./scripts/production.sh

# Restart containers
./vendor/bin/sail -f docker-compose.staging.yml up --scale app=4 -d

# Install dependencies. (This already exists so we will sort this out very soon)
./vendor/bin/sail composer install --optimize-autoloader

# Clear all old application configuration cache
./vendor/bin/sail artisan optimize:clear

# Cache application with new configuration
./vendor/bin/sail artisan optimize

# Run migrations
./vendor/bin/sail artisan migrate --force