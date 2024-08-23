#!/bin/bash

# Clone Piwigo if it doesn't exist
if [ ! -d "piwigo2/.git" ]; then
    git clone --branch 14.x https://github.com/darktorres/piwigo piwigo2
    cd piwigo2
    composer install
    npm install
    chown -R www-data:www-data .
    setfacl -Rdm u:www-data:rwx .
fi

# Execute the command
exec "$@"
