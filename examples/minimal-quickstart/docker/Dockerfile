# Toolchain for the quickstart try-it flow: PHP 8.4 + Composer + Node 22 +
# Python 3 (the A2 acceptance runners need all three), on Debian bookworm.
FROM php:8.4-cli
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev zlib1g-dev libzip-dev python3 ca-certificates curl gnupg \
 && docker-php-ext-install intl zip \
 && mkdir -p /etc/apt/keyrings \
 && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
 && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends nodejs \
 && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
