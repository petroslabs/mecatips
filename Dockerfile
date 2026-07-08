# =============================================================================
# MécaTips — FrankenPHP + PHP 8.4
# =============================================================================
# Multi-stage : base commune → prod (code intégré à l'image) / dev (bind-mount
# du code depuis l'hôte, cf. compose.override.yaml)
# =============================================================================

FROM dunglas/frankenphp:php8.4 AS frankenphp_base

WORKDIR /app

# unzip : requis par Composer pour installer les paquets depuis leurs archives dist
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

# pdo_pgsql : requis par doctrine/doctrine-bundle (DATABASE_URL postgresql://…)
# opcache : perf PHP, utile en dev comme en prod (config prod affinée sur le stage prod)
RUN install-php-extensions pdo_pgsql opcache

# Composer : absent de l'image de base
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Traefik termine déjà le TLS (mkcert en dev, Let's Encrypt en prod) : on
# désactive la gestion HTTPS automatique de Caddy en interne, sinon son port
# 80 ne fait que rediriger vers son propre 443 (boucle 308).
ENV SERVER_NAME=:80

# =============================================================================
# Stage prod : code applicatif intégré à l'image (immuable), assets compilés
# au build. APP_SECRET ci-dessous (et le DATABASE_URL placeholder du .env
# commité) ne servent qu'à faire tourner les commandes bin/console pendant le
# build (compilation du kernel, aucune connexion DB requise) — les vraies
# valeurs sont injectées à l'exécution via l'environnement du conteneur et
# prennent le dessus, sans jamais être écrites dans l'image.
# =============================================================================
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod \
    APP_SECRET=build_time_placeholder

# Le code ne change plus après déploiement dans ce stage : autant désactiver
# la vérification des timestamps pour qu'opcache ne restate jamais les fichiers.
RUN echo 'opcache.validate_timestamps=0' > $PHP_INI_DIR/conf.d/opcache-prod.ini

COPY --chown=www-data:www-data . /app

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && php bin/console asset-map:compile \
    && chown -R www-data:www-data var

# /data et /config : stockage par défaut de Caddy (PKI, autosave...), doit
# être accessible en écriture par l'utilisateur non-root ci-dessous.
RUN mkdir -p /data /config && chown -R www-data:www-data /data /config

USER www-data

# =============================================================================
# Stage dev : image "outillage" seule, code fourni par le bind-mount de
# compose.override.yaml (hot-reload sans rebuild)
# =============================================================================
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
