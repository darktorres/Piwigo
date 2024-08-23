# Base image
FROM ubuntu:24.04

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive

# =================== libvips =============================

RUN apt-get update && apt-get install -y \
    build-essential \
    software-properties-common \
    ninja-build \
    python3-pip \
    pkg-config \
    wget \
    meson

WORKDIR /usr/local/src
ENV LD_LIBRARY_PATH=/usr/local/lib

# Dependencies to build libvips
RUN apt-get install -y \
    libexpat-dev \
    librsvg2-dev \
    libarchive-dev \
    libexif-dev \
    liblcms2-dev \
    libheif-dev \
    libhwy-dev \
    libwebp-dev

# Build libvips from the stable 8.15 branch
ARG VIPS_BRANCH=8.15
ARG VIPS_URL=https://github.com/libvips/libvips/tarball

RUN mkdir libvips-${VIPS_BRANCH} \
    && cd libvips-${VIPS_BRANCH} \
    && wget ${VIPS_URL}/${VIPS_BRANCH} -O - | \
    tar xfz - --strip-components 1

RUN cd libvips-${VIPS_BRANCH} \
    && rm -rf build \
    && meson setup build --libdir lib -Dintrospection=disabled -Dexamples=false \
    && cd build \
    && ninja \
    && ninja test \
    && ninja install

# =================== Piwigo ==============================

# Install dependencies and PHP 8.3
RUN apt-get update && apt-get install -y \
    nginx \
    software-properties-common \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update && apt-get install -y \
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
    php8.3-xml \
    php8.3-zip \
    curl \
    zip \
    unzip \
    git \
    nano \
    && apt-get clean

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

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
pm.max_children = 32 \n\
pm.start_servers = 2 \n\
pm.min_spare_servers = 1 \n\
pm.max_spare_servers = 3 \n\
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
;xdebug \n\
xdebug.mode = develop,debug,coverage \n\
xdebug.collect_return = true \n\
xdebug.collect_assignments = true \n\
xdebug.start_with_request = default \n\
xdebug.output_dir = "C:\Apache24\logs\php" \n\
' > /etc/php/8.3/fpm/php.ini

# Set up working directory
WORKDIR /var/www/html

# Start Nginx and PHP-FPM
CMD ["sh", "-c", "service php8.3-fpm start && nginx -g 'daemon off;'"]
