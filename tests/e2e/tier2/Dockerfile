# WHMCS publishes no Docker image, and its PHP is ionCube-encoded, so the
# runtime needs the ionCube Loader for the exact PHP version. Everything here
# is the stock, freely available loader - no attempt is made to alter WHMCS or
# its licensing, which its EULA forbids.
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libicu-dev libxml2-dev libcurl4-openssl-dev \
        unzip curl default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mysqli gd zip intl bcmath soap opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# ionCube Loader, matched to the image's PHP version and architecture.
RUN set -eux; \
    arch="$(dpkg --print-architecture)"; \
    case "$arch" in \
      arm64)  file=ioncube_loaders_lin_aarch64.tar.gz ;; \
      amd64)  file=ioncube_loaders_lin_x86-64.tar.gz ;; \
      *) echo "unsupported architecture: $arch" >&2; exit 1 ;; \
    esac; \
    curl -fsSL -o /tmp/ioncube.tar.gz "https://downloads.ioncube.com/loader_downloads/$file"; \
    tar -xzf /tmp/ioncube.tar.gz -C /tmp; \
    phpver="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"; \
    extdir="$(php -r 'echo ini_get("extension_dir");')"; \
    cp "/tmp/ioncube/ioncube_loader_lin_${phpver}.so" "$extdir/"; \
    printf 'zend_extension=%s/ioncube_loader_lin_%s.so\n' "$extdir" "$phpver" \
      > /usr/local/etc/php/conf.d/00-ioncube.ini; \
    rm -rf /tmp/ioncube /tmp/ioncube.tar.gz; \
    php -v

# WHMCS is unhappy with low limits during install.
RUN { echo 'memory_limit=512M'; echo 'max_execution_time=600'; \
      echo 'display_errors=Off'; echo 'log_errors=On'; } \
    > /usr/local/etc/php/conf.d/zz-whmcs.ini
