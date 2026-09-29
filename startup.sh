#!/bin/bash

# Move to project folder
cd ~/var/wwww/gca || { echo "❌ Project folder not found"; exit 1; }

# Fix permissions for storage and bootstrap/cache
echo "🔧 Fixing permissions for storage and bootstrap/cache..."
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Start PHP-FPM service
echo "▶️ Starting PHP-FPM service..."
sudo service php8.3-fpm start || { echo "❌ Failed to start PHP-FPM"; exit 1; }

# Start Nginx service
echo "▶️ Starting Nginx service..."
sudo service nginx start || { echo "❌ Failed to start Nginx"; exit 1; }

# Free up port 6001 if already occupied (WebSocket)
if sudo lsof -i :6001 -sTCP:LISTEN -t >/dev/null; then
  echo "⚡ Port 6001 busy, killing existing process..."
  sudo kill -9 $(sudo lsof -i :6001 -sTCP:LISTEN -t)
fi

# Free up port 8000 if already occupied (Octane)
if sudo lsof -i :8000 -sTCP:LISTEN -t >/dev/null; then
  echo "⚡ Port 8000 busy, killing existing process..."
  sudo kill -9 $(sudo lsof -i :8000 -sTCP:LISTEN -t)
fi

# Start Laravel Octane server (with Swoole)
echo "🚀 Starting Laravel Octane server on port 8000..."
nohup php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 > storage/logs/octane.log 2>&1 &

# Start WebSocket server
echo "🚀 Starting Laravel WebSocket server on port 6001..."
nohup php artisan websocket:serve > storage/logs/websocket.log 2>&1 &

echo "✅ All services started successfully!"

