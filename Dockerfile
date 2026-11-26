# Produktions-Image für Atoll (Profil «Generic»: Port 8000, Health-Check /metrics).
# Baut ohne Build-Argumente und ohne Secrets: alle Einstellungen kommen zur Laufzeit
# als Umgebungsvariablen (siehe .env.example). Es wird keine .env ins Image kopiert.
FROM php:8.3-apache

# GD (JPG/PNG) zum Verkleinern grosser Fotos der Ausweiskopien: Graph nimmt mit «Mail.Send»
# nur rund 3 MB Anhang pro Mail an. Ohne GD läuft die App trotzdem, verkleinert aber nicht.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd \
    && rm -rf /var/lib/apt/lists/*

# Apache: Port 8000, Document-Root = public/, .htaccess auswerten, nützliche Module.
# Kein Composer: die App hat keine Laufzeit-Abhängigkeiten ausser der PHP-Erweiterung GD.
# Upload der Ausweiskopien: PHP-Grenzen etwas über UPLOAD_MAX_MB (5 MB) und UPLOAD_MAX_FILES (6);
# post_max_size 40M = 6 Dateien × 5 MB + Reserve. Die eigentliche Prüfung macht die App
# (src/Order/UploadValidator.php). memory_limit 256M: Verkleinern von Fotos bis ca. 40 Megapixel.
RUN a2enmod rewrite headers expires \
    && sed -ri 's/Listen 80$/Listen 8000/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8000>/' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/notariat.conf \
    && a2enconf notariat \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/security-extra.conf \
    && a2enconf security-extra \
    && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\n' \
        > /usr/local/etc/php/conf.d/notariat.ini \
    && printf 'file_uploads=On\nupload_max_filesize=6M\npost_max_size=40M\nmax_file_uploads=10\nmemory_limit=256M\n' \
        >> /usr/local/etc/php/conf.d/notariat.ini

WORKDIR /var/www/html
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY lang/ lang/

# Läuft nicht als root. Port 8000 ist ein unprivilegierter Port.
USER www-data
EXPOSE 8000
