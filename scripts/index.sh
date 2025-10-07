#!/usr/bin/env bash
set -e
set -o pipefail

echo "🚀 Starting production deployment..."

# 1. Pull latest code
echo "🔄 Updating codebase..."
git fetch origin main
git reset --hard origin/main

# 2. Fix permissions
echo "🔧 Setting folder permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache

# 3. Stop running containers
echo "🧹 Stopping old containers..."
./vendor/bin/sail -f docker-compose.staging.yml down --remove-orphans

# 4. Switch to production mode
echo "⚙️ Setting environment to production..."
./scripts/production.sh

# 5. Build and start containers
echo "🐳 Starting Docker containers..."
./vendor/bin/sail -f docker-compose.staging.yml up --scale app=4 -d

# 6. Install dependencies
echo "📦 Installing dependencies..."
./vendor/bin/sail composer install --optimize-autoloader --no-dev

# 7. Clear & rebuild caches
echo "🧹 Optimizing application..."
./vendor/bin/sail artisan optimize:clear
./vendor/bin/sail artisan optimize

# 8. Run migrations
echo "🗄️ Running database migrations..."
./vendor/bin/sail artisan migrate --force

# 9. Restart Horizon
echo "⚡ Restarting Horizon..."
./vendor/bin/sail artisan horizon:terminate || true

echo "✅ Deployment complete!"


