#!/bin/bash
set -euo pipefail

cd /var/www/html

# SSH server (key-only). Copy mounted key with strict perms sshd requires.
if [ -f /run/ssh-keys/authorized_keys ]; then
  mkdir -p /etc/ssh/authorized_keys
  install -m 600 -o root -g root /run/ssh-keys/authorized_keys /etc/ssh/authorized_keys/root
  # Make container env (DB_*, PUSHER_*) available to SSH sessions
  env | grep -E '^(APP_|DB_|PUSHER_|BROADCAST_|PHP_|COMPOSER_)' | sed 's/^/export /; s/=\(.*\)$/="\1"/' > /etc/profile.d/container-env.sh
  chmod 600 /etc/profile.d/container-env.sh
  /usr/sbin/sshd
  echo "SSH server started on :22"
fi

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chmod -R ug+rwx storage bootstrap/cache || true

if [ ! -f vendor/autoload.php ]; then
  echo "Installing PHP dependencies..."
  composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -f public/mix-manifest.json ]; then
  echo "Building frontend assets..."
  if [ ! -d node_modules ]; then
    npm install --no-fund --no-audit
  fi
  npx mix --production
fi

echo "Waiting for MariaDB..."
for i in $(seq 1 60); do
  if php -r '
    $host = getenv("DB_HOST") ?: "mysql";
    $db = getenv("DB_DATABASE") ?: "sabong_lara_db";
    $user = getenv("DB_USERNAME") ?: "sabong_root";
    $pass = getenv("DB_PASSWORD");
    try {
      new PDO("mysql:host=$host;port=3306;dbname=$db", $user, $pass);
      exit(0);
    } catch (Throwable $e) {
      exit(1);
    }
  '; then
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "MariaDB did not become ready in time."
    exit 1
  fi
  sleep 2
done

# PHP-FPM and the WebSocket server run as www-data: give it the folders
# Laravel writes to (logs, sessions, cache, keys, backups, closing reports).
touch storage/logs/laravel.log
chown -R www-data:www-data storage bootstrap/cache

artisan config:clear
artisan migrate --force --no-interaction || echo "WARNING: migrations failed; check the log above."

echo "Starting nginx (:80), PHP-FPM and the WebSocket server (:6001) under supervisord"
exec /usr/bin/supervisord -c /etc/supervisor/sabonglara.conf
