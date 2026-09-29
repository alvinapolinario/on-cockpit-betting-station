# Sabonglara app: nginx + PHP-FPM + WebSocket server, managed by supervisord.
FROM php:8.1-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

# This network blocks plain-HTTP access to deb.debian.org; fetch packages over HTTPS.
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        nginx \
        supervisor \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        openssh-server \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        pcntl \
        sockets \
        intl \
        gd \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# PHP: production defaults + our overrides; FPM pool runs as www-data.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY php.ini "$PHP_INI_DIR/conf.d/zz-sabonglara.ini"
COPY php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-sabonglara-pool.conf

# nginx serves the app; the stock site is removed.
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf
COPY nginx.conf /etc/nginx/conf.d/sabonglara.conf

COPY supervisord.conf /etc/supervisor/sabonglara.conf

# `artisan ...` runs Laravel commands as www-data, so files they create
# (backups, keys, logs) stay readable by the web server.
RUN printf '#!/bin/sh\nexec runuser -u www-data -- php /var/www/html/artisan "$@"\n' > /usr/local/bin/artisan \
    && chmod +x /usr/local/bin/artisan \
    && mkdir -p /var/www/.config \
    && chown www-data:www-data /var/www/.config

# SSH access: key-only, no passwords
RUN mkdir -p /run/sshd /root/.ssh \
    && chmod 700 /root/.ssh \
    && ssh-keygen -A \
    && printf '%s\n' \
        'PermitRootLogin prohibit-password' \
        'PasswordAuthentication no' \
        'KbdInteractiveAuthentication no' \
        'PubkeyAuthentication yes' \
        'AuthorizedKeysFile /etc/ssh/authorized_keys/%u' \
        'X11Forwarding no' \
        'AllowAgentForwarding no' \
        > /etc/ssh/sshd_config.d/sabonglara.conf \
    && echo 'cd /var/www/html' >> /root/.bashrc

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 22 80 6001

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/login || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
