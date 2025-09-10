#!/usr/bin/env bash

# Change the application environment to production
function change_environment_to_production {
    sed -i 's/^APP_ENV=.*/APP_ENV=production/' ./.env && echo "Application is now in production."
}

# Disable debug mode in application
function disable_debug_mode {
    sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' ./.env && echo "Application debug mode is disabled."
}