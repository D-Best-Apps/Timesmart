FROM php:8.2-fpm

# ---- OS deps ----
# Add --no-install-recommends and include JPEG/WebP/Freetype for proper GD support.
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    git \
    supervisor \
    unzip \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
  && rm -rf /var/lib/apt/lists/*

# ---- PHP extensions ----
# Build GD with JPEG/WebP/Freetype; also install PDO MySQL, MySQLi, Zip
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd pdo_mysql mysqli zip
# Optional perf: enable opcache
# RUN docker-php-ext-install opcache

# ---- Composer (allow root) ----
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ---- Nginx & Supervisor config ----
COPY nginx.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default \
 && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ---- App code ----
WORKDIR /var/www/html
RUN git config --global --add safe.directory /var/www/html

# Cache-buster lets you force a fresh clone at build time
ARG CACHE_BUST=initial
# Optionally: ARG APP_BRANCH=main
RUN find . -mindepth 1 -delete \
 && git clone --depth 1 https://github.com/D-Best-Apps/Timesmart.git . 
    # For a specific branch, add: --branch ${APP_BRANCH}

# ---- PHP deps ----
RUN composer install --no-dev --no-interaction --optimize-autoloader

# ---- Environment (prefer runtime envs over baking .env) ----
# If you truly need it inside the image, leave this line; otherwise comment it out
# and rely on docker-compose `env_file:` instead.
# COPY .env .env

# ---- Permissions ----
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Start Supervisor (which starts php-fpm -F and nginx -g "daemon off;")
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]