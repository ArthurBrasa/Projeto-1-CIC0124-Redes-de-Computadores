# 1) Front-end assets. The Reverb public key is compiled into the JS.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
ENV VITE_REVERB_APP_KEY=pulse-demo-key VITE_REVERB_PORT=8080 VITE_REVERB_SCHEME=http
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm install --no-audit --no-fund && npm run build

# 2) PHP dependencies.
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

# 3) Runtime. iproute2 (tc netem) and tcpdump are used by the network experiments.
FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends iproute2 tcpdump iputils-ping curl procps \
    && docker-php-ext-install pcntl \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database/data \
    && php artisan package:discover --ansi
ENTRYPOINT ["/app/docker/entrypoint.sh"]
EXPOSE 8000 8080 9601 9601/udp
