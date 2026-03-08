FROM php:8.3-fpm

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    nano \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure PHP for large file uploads
RUN echo "upload_max_filesize = 500M" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 500M" >> /usr/local/etc/php/php.ini

WORKDIR /var/www/html