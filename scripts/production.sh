#!/usr/bin/env bash

# Load the functions used by the bash application
source ./scripts/functions.sh

# Change the enviroment to production
change_environment_to_production

# Disable debug mode
disable_debug_mode