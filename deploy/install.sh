#!/bin/bash
# Photowall: sets up or updates everything on this server. Safe to run again at any time.
# Normally you do not run this yourself: pw-deploy runs it after fetching the code.
set -u
APP=$(cd "$(dirname "$0")/.." && pwd)
DATA=/var/lib/photowall
VENV=/opt/photowall-venv
CONF=/etc/photowall/config.php
WEBUSER=www-data
H_RESP=${PW_HOST_RESPONDENT:-photowall.rewardhours.com}
H_ADMIN=${PW_HOST_ADMIN:-console.photowall.rewardhours.com}
H_CLIENT=${PW_HOST_CLIENT:-client.photowall.researchandmetric.com}

say()  { echo; echo "=== $*"; }
stop() { echo; echo "STOPPED: $*"; echo "Nothing after this step ran. Paste me the lines above."; exit 1; }
[ "$(id -u)" = 0 ] || stop "please run this as root (sudo -i first)."
export DEBIAN_FRONTEND=noninteractive

say "1/8 Checking what this server already has"
NEED=""
command -v php >/dev/null      || NEED="$NEED php-cli"
command -v exiftool >/dev/null || NEED="$NEED libimage-exiftool-perl"
command -v curl >/dev/null     || NEED="$NEED curl"
python3 -c 'import venv, ensurepip' 2>/dev/null || NEED="$NEED python3-venv"
command -v mysql >/dev/null || command -v mariadb >/dev/null || NEED="$NEED mariadb-server"
WEB=""
if command -v apache2 >/dev/null || [ -d /etc/apache2 ]; then WEB=apache
elif command -v nginx >/dev/null; then WEB=nginx
else WEB=apache; NEED="$NEED apache2 libapache2-mod-php"; fi
if [ -n "$NEED" ]; then
  echo "Installing:$NEED"
  apt-get update -qq && apt-get install -y -qq $NEED >/dev/null || stop "could not install:$NEED"
fi
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
EXT=""
for e in pdo_mysql curl mbstring gd; do
  php -m | grep -qix "$e" || EXT="$EXT php$PHPV-$( [ "$e" = pdo_mysql ] && echo mysql || echo "$e" )"
done
if [ -n "$EXT" ]; then
  echo "Installing PHP parts:$EXT"
  apt-get update -qq && apt-get install -y -qq $EXT >/dev/null || stop "could not install:$EXT"
fi
MYSQL=$(command -v mysql || command -v mariadb)
echo "Web server: $WEB, PHP $PHPV, database client: $MYSQL"

say "2/8 Storage folders (photos are kept outside the web folder)"
for d in originals previews thumbs tmp logs models sessions; do mkdir -p "$DATA/$d"; done
chown -R "$WEBUSER":"$WEBUSER" "$DATA" && chmod -R u=rwX,g=rX,o= "$DATA"
echo "Ready: $DATA"

say "3/8 Photo scanner (runs on this server, no outside service)"
[ -x "$VENV/bin/python" ] || python3 -m venv "$VENV" || stop "could not create the Python environment."
WANT=$(sha256sum "$APP/scanner/requirements.txt" | cut -d' ' -f1)
if [ "$(cat "$VENV/.photowall-req" 2>/dev/null)" != "$WANT" ]; then
  echo "Installing the scanner libraries (this takes a few minutes the first time)"
  "$VENV/bin/pip" install --quiet --upgrade pip >/dev/null 2>&1
  "$VENV/bin/pip" install --quiet -r "$APP/scanner/requirements.txt" || stop "could not install the scanner libraries."
  echo "$WANT" > "$VENV/.photowall-req"
fi
fetch_model() { # name, expected sha256
  local f="$DATA/models/$1"
  if [ -f "$f" ] && [ "$(sha256sum "$f" | cut -d' ' -f1)" = "$2" ]; then return 0; fi
  curl -fsSL --retry 2 -o "$f.part" "https://raw.githubusercontent.com/yahoo/open_nsfw/master/nsfw_model/$1" \
    && [ "$(sha256sum "$f.part" | cut -d' ' -f1)" = "$2" ] && mv "$f.part" "$f" && chown "$WEBUSER":"$WEBUSER" "$f" && return 0
  rm -f "$f.part"; return 1
}
fetch_model deploy.prototxt a359e2d7b23abca938bb52580acf578c1b78870263fea814afa294fb4f315a4b \
  && fetch_model resnet_50_1by2_nsfw.caffemodel 74b7c0374425c2b324f73b134f8a7254eab9bbddc9e452a22d934b8c17015ad2 \
  && echo "Safety models are in place." \
  || echo "NOTE: could not download safety model 1 (OpenNSFW). The scan still runs with model 2; run pw-deploy again later to retry."

# Models that suggest the category of a photo (a general image classifier and a face detector).
fetch_cat() { # name, path on GitHub, expected sha256
  local f="$DATA/models/$1"
  if [ -f "$f" ] && [ "$(sha256sum "$f" | cut -d' ' -f1)" = "$3" ]; then return 0; fi
  curl -fsSL --retry 2 -o "$f.part" "https://media.githubusercontent.com/media/$2" \
    && [ "$(sha256sum "$f.part" | cut -d' ' -f1)" = "$3" ] && mv "$f.part" "$f" && chown "$WEBUSER":"$WEBUSER" "$f" && return 0
  rm -f "$f.part"; return 1
}
fetch_cat efficientnet-lite4-11.onnx onnx/models/main/validated/vision/classification/efficientnet-lite4/model/efficientnet-lite4-11.onnx d111689907c06eea7c82e4833ddef758da6453b9d4cf60b7e99ca05c7cbd9c12 \
  && fetch_cat face_detection_yunet_2023mar.onnx opencv/opencv_zoo/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx 8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4 \
  && echo "Category models are in place." \
  || echo "NOTE: could not download the models that detect a photo's category. Uploads still work; photos are filed under Other until they arrive. Run pw-deploy again later to retry."

say "4/8 Settings and database"
if [ ! -f "$CONF" ]; then
  mkdir -p /etc/photowall
  DBPASS=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
  SECRET=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
  "$MYSQL" -e "CREATE DATABASE IF NOT EXISTS photowall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'pw_app'@'localhost' IDENTIFIED BY '$DBPASS';
    ALTER USER 'pw_app'@'localhost' IDENTIFIED BY '$DBPASS';
    GRANT ALL PRIVILEGES ON photowall.* TO 'pw_app'@'localhost'; FLUSH PRIVILEGES;" \
    || stop "could not create the database. Check that 'mysql' works for root on this server (try: mysql -e 'select 1')."
  sed -e "s/'pass' => 'CHANGE_ME'/'pass' => '$DBPASS'/" \
      -e "s/CHANGE_ME_TO_A_LONG_RANDOM_STRING/$SECRET/" \
      -e "s/'respondent' => '[^']*'/'respondent' => '$H_RESP'/" \
      -e "s/'admin'      => '[^']*'/'admin'      => '$H_ADMIN'/" \
      -e "s/'client'     => '[^']*'/'client'     => '$H_CLIENT'/" \
      -e "s#'python'   => '[^']*'#'python'   => '$VENV/bin/python'#" \
      -e "s#'exiftool' => '[^']*'#'exiftool' => '$(command -v exiftool)'#" \
      "$APP/config.sample.php" > "$CONF"
  echo "Created $CONF and the database 'photowall'."
else
  echo "Keeping the existing settings in $CONF."
fi
chown root:"$WEBUSER" "$CONF" && chmod 640 "$CONF"
php "$APP/bin/migrate.php" || stop "the database update failed."

say "5/8 Web server"
SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | sort -V | tail -1)
if [ "$WEB" = apache ]; then
  SITE=/etc/apache2/sites-available/photowall.conf
  if [ ! -f "$SITE" ] && [ ! -f /etc/apache2/sites-enabled/photowall.conf ]; then
    cat > "$SITE" <<EOF
# Photowall: respondent site, staff console and client portal share one app.
<VirtualHost *:80>
    ServerName $H_RESP
    ServerAlias $H_ADMIN $H_CLIENT
    DocumentRoot $APP/public
    LimitRequestBody 78643200
    <Directory $APP/public>
        Options -Indexes
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>
    <Files ".user.ini">
        Require all denied
    </Files>
    <IfModule mod_php.c>
        php_admin_value upload_max_filesize 64M
        php_admin_value post_max_size 70M
        php_admin_value max_execution_time 180
        php_admin_value memory_limit 256M
    </IfModule>
    ErrorLog \${APACHE_LOG_DIR}/photowall-error.log
    CustomLog \${APACHE_LOG_DIR}/photowall-access.log combined
</VirtualHost>
EOF
    a2ensite -q photowall >/dev/null 2>&1 || ln -sf "$SITE" /etc/apache2/sites-enabled/photowall.conf
    echo "Added the Apache site for $H_RESP, $H_ADMIN and $H_CLIENT."
  else
    echo "Keeping the existing Apache site."
  fi
  # Speed: text is sent compressed, and files under /assets are kept by browsers for a long time (their
  # addresses change whenever they do). Both rules apply inside this application's folder only.
  SPEED=/etc/apache2/conf-available/photowall-speed.conf
  a2enmod -q deflate headers >/dev/null 2>&1
  cat > "$SPEED" <<EOF
# Photowall: written again on every pw-deploy. Compression and browser caching for this application only.
<Directory $APP/public>
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json application/xml image/svg+xml
    </IfModule>
    <IfModule mod_brotli.c>
        AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript application/javascript application/json application/xml image/svg+xml
    </IfModule>
</Directory>
<Directory $APP/public/assets>
    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=31536000, immutable"
    </IfModule>
</Directory>
EOF
  a2enconf -q photowall-speed >/dev/null 2>&1
  if apache2ctl configtest >/dev/null 2>&1; then
    echo "Speed settings for Apache are in place (compression, long-lived files)."
  else
    a2disconf -q photowall-speed >/dev/null 2>&1; rm -f "$SPEED"
    echo "NOTE: Apache did not accept the speed settings, so they were left out. Everything else is unchanged."
  fi
  apache2ctl configtest >/dev/null 2>&1 || stop "Apache does not accept its configuration. Run: apache2ctl configtest"
  systemctl reload apache2 2>/dev/null || apache2ctl graceful >/dev/null 2>&1 || echo "NOTE: could not reload Apache; reload it yourself."
else
  SITE=/etc/nginx/sites-available/photowall
  if [ ! -f "$SITE" ]; then
    [ -n "$SOCK" ] || stop "nginx needs PHP-FPM, and no PHP-FPM socket was found in /run/php. Install it: apt-get install php$PHPV-fpm"
    cat > "$SITE" <<EOF
# Photowall: respondent site, staff console and client portal share one app.
server {
    listen 80;
    server_name $H_RESP $H_ADMIN $H_CLIENT;
    root $APP/public;
    index index.php;
    client_max_body_size 75m;
    gzip on;
    gzip_types text/plain text/css text/javascript application/javascript application/json application/xml image/svg+xml;
    location /assets/ { add_header Cache-Control "public, max-age=31536000, immutable"; }
    location / { try_files \$uri /index.php?\$query_string; }
    location ~ /\. { deny all; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$SOCK;
        fastcgi_read_timeout 300;
    }
}
EOF
    ln -sf "$SITE" /etc/nginx/sites-enabled/photowall
    echo "Added the nginx site for $H_RESP, $H_ADMIN and $H_CLIENT."
  else
    echo "Keeping the existing nginx site."
  fi
  nginx -t >/dev/null 2>&1 || stop "nginx does not accept its configuration. Run: nginx -t"
  systemctl reload nginx 2>/dev/null || nginx -s reload 2>/dev/null || echo "NOTE: could not reload nginx; reload it yourself."
fi
[ -n "$SOCK" ] && systemctl reload "$(basename "$SOCK" .sock)" 2>/dev/null

php "$APP/bin/build.php" || echo "NOTE: the small stylesheet could not be made; pages use the full one, which works the same."

say "6/8 Commands and hourly clean-up"
install -m 755 "$APP/deploy/pw-deploy" /usr/local/bin/pw-deploy
printf '#!/bin/bash\nexec runuser -u %s -- php %s/bin/admin.php "$@"\n' "$WEBUSER" "$APP" > /usr/local/bin/pw-admin
printf '#!/bin/bash\nexec runuser -u %s -- php %s/bin/selftest.php "$@"\n' "$WEBUSER" "$APP" > /usr/local/bin/pw-check
printf '#!/bin/bash\n# setup and off change the settings file, so they run as root; the rest runs as the web user\ncase "${1:-}" in setup|off) exec php %s/bin/spaces.php "$@";; *) exec runuser -u %s -- php %s/bin/spaces.php "$@";; esac\n' "$APP" "$WEBUSER" "$APP" > /usr/local/bin/pw-spaces
chmod 755 /usr/local/bin/pw-admin /usr/local/bin/pw-check /usr/local/bin/pw-spaces
echo "17 * * * * $WEBUSER php $APP/bin/cron.php >/dev/null 2>&1" > /etc/cron.d/photowall
# every 10 minutes: email contributors whose photos were accepted
echo "*/10 * * * * $WEBUSER php $APP/bin/notify.php >/dev/null 2>&1" >> /etc/cron.d/photowall
echo "Commands: pw-deploy, pw-admin, pw-check, pw-spaces"

say "7/8 Secure connection (https)"
if [ -d "/etc/letsencrypt/live/$H_RESP" ]; then
  echo "A certificate for $H_RESP is already in place."
else
  MISSING=""
  for h in "$H_RESP" "$H_ADMIN" "$H_CLIENT"; do getent hosts "$h" >/dev/null || MISSING="$MISSING $h"; done
  CERTCMD="certbot --$WEB --redirect -d $H_RESP -d $H_ADMIN -d $H_CLIENT"
  if [ -n "$MISSING" ]; then
    echo "These names do not point to a server yet:$MISSING"
    echo "Add a DNS A record for each of the three names to this server's public IP address, then run: pw-deploy"
  elif command -v certbot >/dev/null; then
    $CERTCMD -n && echo "Certificate installed." || { echo "The certificate could not be issued automatically. Run this and follow it:"; echo "  $CERTCMD"; }
  else
    echo "certbot is not installed. Install it (apt-get install certbot python3-certbot-$WEB), then run:"; echo "  $CERTCMD"
  fi
fi

say "8/8 Final check"
runuser -u "$WEBUSER" -- php "$APP/bin/selftest.php"
echo
if [ "$("$MYSQL" -N -B -e 'SELECT COUNT(*) FROM photowall.staff' 2>/dev/null)" = "0" ]; then
  echo "Next: create your own console account (it prints a password):"
  echo "  pw-admin add YOUR_EMAIL \"Your Name\""
fi
echo "Photowall is at version $(git -C "$APP" log -1 --format='%h %s' 2>/dev/null || echo unknown)."
exit 0
