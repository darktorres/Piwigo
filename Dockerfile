FROM ubuntu:24.04

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive

# Install prerequisites and add Ondrej PHP PPA
RUN apt-get update && apt-get install -y --no-install-recommends \
    sudo \
    software-properties-common \
    wget \
    gnupg2 \
    lsb-release \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update

# Install runtime dependencies
RUN apt-get install -y --no-install-recommends \
    nginx \
    curl \
    bash-completion \
    nano \
    unzip \
    npm \
    acl \
    git \
    libvips \
    php8.3-cli \
    php8.3-curl \
    php8.3-ffi \
    php8.3-fileinfo \
    php8.3-gd \
    php8.3-intl \
    php8.3-fpm \
    php8.3-mbstring \
    php8.3-exif \
    php8.3-mysqli \
    php8.3-pgsql \
    php8.3-xdebug \
    php8.3-xml \
    php8.3-zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Percona Server
RUN wget https://repo.percona.com/apt/percona-release_latest.generic_all.deb \
    && dpkg -i percona-release_latest.generic_all.deb \
    && apt-get update \
    && percona-release enable ps-84-lts \
    && apt-get install -y percona-server-server

# Install PostgreSQL
RUN apt-get update && apt-get install -y postgresql postgresql-contrib

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Yarn
RUN npm install -g corepack

# Configure Nginx
RUN rm /etc/nginx/sites-enabled/default
RUN echo '\
server { \n\
    listen 80; \n\
    server_name localhost; \n\
\n\
    error_log /dev/stderr; \n\
#    access_log /dev/stdout; \n\
\n\
    root /var/www/html; \n\
    index index.php index.html index.htm; \n\
\n\
    location / { \n\
        try_files $uri $uri/ /index.php?$query_string; \n\
    } \n\
\n\
    location ~ \.php$ { \n\
        include snippets/fastcgi-php.conf; \n\
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock; \n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \n\
        include fastcgi_params; \n\
\n\
        # Set high timeout values \n\
        fastcgi_read_timeout 3600s; \n\
        fastcgi_send_timeout 3600s; \n\
        fastcgi_connect_timeout 3600s; \n\
    } \n\
\n\
    location ~ /\.ht { \n\
        deny all; \n\
    } \n\
} \n\
' > /etc/nginx/conf.d/nginx.conf

# Configure PHP-FPM
RUN echo '\
[www] \n\
user = www-data \n\
group = www-data \n\
listen = /var/run/php/php8.3-fpm.sock \n\
listen.owner = www-data \n\
listen.group = www-data \n\
pm = dynamic \n\
pm.max_children = 50 \n\
pm.start_servers = 10 \n\
pm.min_spare_servers = 5 \n\
pm.max_spare_servers = 20 \n\
' > /etc/php/8.3/fpm/pool.d/www.conf 

# Inline custom PHP configuration
RUN echo '\
; libvips \n\
zend.max_allowed_stack_size = -1 \n\
ffi.enable = true \n\
; piwigo \n\
max_execution_time = 0 \n\
max_input_vars = 100000 \n\
memory_limit = -1 \n\
error_reporting = E_ALL \n\
error_log = /dev/stderr \n\
;opcache \n\
pcre.jit=1 \n\
opcache.enable=1 \n\
opcache.enable_cli=1 \n\
opcache.jit_buffer_size=256M \n\
opcache.jit=1235 \n\
opcache.file_cache=/var/www/php_opcache \n\
opcache.file_cache_only=0 \n\
opcache.file_cache_consistency_checks=0 \n\
;xdebug \n\
xdebug.mode = develop,debug,coverage \n\
xdebug.collect_return = true \n\
xdebug.collect_assignments = true \n\
xdebug.start_with_request = default \n\
xdebug.output_dir = "/var/log/php" \n\
' > /etc/php/8.3/fpm/php.ini

# Comment out opcache.jit=off in the opcache.ini file
RUN sed -i 's/^opcache.jit=off/;opcache.jit=off/' /etc/php/8.3/mods-available/opcache.ini

# Create opcache file cache folder
RUN mkdir /var/www/php_opcache

# Set up working directory
WORKDIR /var/www/html

# Clone the Piwigo repository only if it doesn't already exist
RUN if [ ! -d "piwigo2/.git" ]; then \
    git clone --branch 14.x https://github.com/darktorres/piwigo piwigo2 && \
    cd piwigo2 && composer install && npm install && \
    chown -R www-data:www-data /var/www/html && \
    setfacl -Rdm u:www-data:rwx /var/www/html && \
    git config --global --add safe.directory /var/www/html/piwigo2; \
fi

# Add skip-networking to disable TCP connections and force socket-only communication
RUN echo '[mysqld]\nskip-networking' >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Run MySQL commands to create the user and grant privileges
RUN /bin/bash -c " \
    set -e; \
    service mysql start; \
    mysql -u root -e \" \
    CREATE USER 'www-data'@'localhost' IDENTIFIED WITH auth_socket; \
    GRANT ALL PRIVILEGES ON *.* TO 'www-data'@'localhost'; \
    FLUSH PRIVILEGES; \
    \""
    
# Run PostgreSQL commands to create the user and grant privileges
RUN /bin/bash -c " \
    set -e; \
    service postgresql start; \
    sudo -u postgres psql -c \" \
    CREATE USER \\\"www-data\\\"; \
    ALTER USER \\\"www-data\\\" WITH SUPERUSER; \
    \""

# Start Nginx and PHP-FPM
CMD ["bash", "-c", "service mysql start && service postgresql start && service php8.3-fpm start && nginx -g 'daemon off;'"]
