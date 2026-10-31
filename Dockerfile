## Dockerfile to build PMSF from this repository
# - The runtime base image can be found here: https://github.com/thecodingmachine/docker-images-php
# - Inside the container, the content of this git repo lives in /var/www/html/
## You have to mount your configs into the container:
# - mount config.php to /var/www/html/config/config.php
# - mount access-config.php to /var/www/html/config/access-config.php
# - Also mount every other configuration file necessary into the according directory.

## Stage 1: build frontend assets (static/dist, index.php, register.php)
FROM node:25-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
# Use the lockfile when one is committed, so builds are reproducible
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY . .
RUN npm run build
# pre-index.php loads these browser libraries straight from node_modules,
# so only they are carried into the runtime image
RUN mkdir -p /app/web_modules && for pkg in bootstrap bootstrap-icons datatables flag-icons leaflet \
        leaflet-geosearch leaflet.markercluster long moment push.js; do \
        cp -r "node_modules/$pkg" /app/web_modules/; \
    done

## Stage 2: runtime image without Node, npm or build tooling
FROM thecodingmachine/php:8.3-v5-apache
COPY --chown=docker:docker . /var/www/html/
COPY --from=assets --chown=docker:docker /app/static/dist /var/www/html/static/dist
COPY --from=assets --chown=docker:docker /app/index.php /app/register.php /var/www/html/
COPY --from=assets --chown=docker:docker /app/web_modules /var/www/html/node_modules
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader
