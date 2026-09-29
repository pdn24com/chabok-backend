FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer

FROM php:8.3-cli-bookworm@sha256:a4fcf31ffb94b8d19b84514926b5a2cddf22a38b1e29922f8d3e8a933091f806

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        git \
        libxml2-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        dom \
        mbstring \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
