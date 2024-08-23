# Use Ubuntu 24.04 as the base image
FROM ubuntu:24.04

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive

# Install prerequisites and PHP packages in a single RUN step to reduce layers
RUN apt-get update && \
    apt-get install -y --no-install-recommends software-properties-common && \
    add-apt-repository ppa:ondrej/php && \
    apt-get install -y --no-install-recommends \
    acl \
    bash-completion \
    curl \
    git \
    gnupg2 \
    libvips \
    lsb-release \
    nano \
    nginx \
    sudo \
    unzip \
    wget \
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
    php8.3-zip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Percona Server
RUN wget https://repo.percona.com/apt/percona-release_latest.generic_all.deb \
    && dpkg -i percona-release_latest.generic_all.deb \
    && percona-release enable ps-84-lts \
    && apt-get install -y percona-server-server \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PostgreSQL
RUN apt-get update && apt-get install -y postgresql postgresql-contrib \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install NVM (Node Version Manager) and Node.js
ENV NVM_DIR=/root/.nvm \
    NODE_VERSION=v20.18.0

RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.5/install.sh | bash && \
    . "$NVM_DIR/nvm.sh" && \
    nvm install $NODE_VERSION && \
    nvm use $NODE_VERSION && \
    npm install -g npm@latest && \
    echo 'export NVM_DIR=$NVM_DIR' >> /root/.bashrc && \
    echo '[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"' >> /root/.bashrc && \
    echo '[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"' >> /root/.bashrc

# Add node and npm to the PATH
ENV PATH="$NVM_DIR/versions/node/$NODE_VERSION/bin:$PATH"

# Append custom MySQL configuration
COPY mysql.conf /tmp/custom-mysql.conf
RUN cat /tmp/custom-mysql.conf >> /etc/mysql/my.cnf

# Remove Nginx default config
RUN rm /etc/nginx/sites-enabled/default

# Append custom Nginx configuration
COPY nginx.conf /tmp/custom-nginx.conf
RUN cat /tmp/custom-nginx.conf >> /etc/nginx/conf.d/nginx.conf && rm /tmp/custom-nginx.conf

# Append to PHP-FPM pool config
COPY php-fpm.conf /tmp/custom-php-fpm.conf
RUN cat /tmp/custom-php-fpm.conf >> /etc/php/8.3/fpm/pool.d/www.conf && rm /tmp/custom-php-fpm.conf

# Append custom PHP configuration
COPY custom-php.ini /tmp/custom-php.ini
RUN cat /tmp/custom-php.ini >> /etc/php/8.3/fpm/php.ini && rm /tmp/custom-php.ini

# Comment out opcache.jit=off in the opcache.ini file
RUN sed -i 's/^opcache.jit=off/;opcache.jit=off/' /etc/php/8.3/mods-available/opcache.ini

# Create opcache file cache folder
RUN mkdir /var/www/php_opcache

# Set git safe directory
RUN git config --global --add safe.directory /var/www/html/piwigo2

# Set up working directory
WORKDIR /var/www/html

# Copy entrypoint script and make it executable
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set the entry point
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Setup MySQL user for 'www-data'
RUN /bin/bash -c " \
    service mysql start && \
    mysql -u root -e \"CREATE USER 'www-data'@'localhost' IDENTIFIED WITH auth_socket; GRANT ALL PRIVILEGES ON *.* TO 'www-data'@'localhost'; FLUSH PRIVILEGES;\""

# Setup PostgreSQL user for 'www-data'
RUN /bin/bash -c " \
    service postgresql start && \
    sudo -u postgres psql -c \"CREATE USER \\\"www-data\\\"; ALTER USER \\\"www-data\\\" WITH SUPERUSER;\""

# Start services
CMD ["bash", "-c", "service mysql start && service postgresql start && service php8.3-fpm start && nginx -g 'daemon off;'"]
