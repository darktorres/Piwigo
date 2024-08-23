#!/bin/bash

# Clone Piwigo if it doesn't exist
if [ ! -d "piwigo-fork/.git" ]; then
    git clone --branch 14.x https://github.com/darktorres/piwigo piwigo-fork
    cd piwigo-fork
    composer install
    npm install
    chown -R www-data:www-data .
    setfacl -Rdm u:www-data:rwx .
fi

# Execute the command
exec "$@"
