#!/usr/bin/env bash
set -e
set -o pipefail

APP_CONTAINER="app"
COMPOSE_FILE="docker-compose.staging.yml"

echo "🚀 Starting production deployment..."

# 1. Pull latest code
echo "🔄 Updating codebase..."
git fetch origin main
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)

if [ "$LOCAL" = "$REMOTE" ]; then
    echo "✅ Codebase is already up-to-date."
    CODE_CHANGED=false
else
    echo "📦 New commits detected."
    git reset --hard origin/main
    CODE_CHANGED=true
fi

# 2. Fix permissions
echo "🔧 Setting folder permissions..."
sudo chmod -R 755 storage bootstrap/cache

# 3. Stop app container if code changed
if [ "$CODE_CHANGED" = true ]; then
    echo "🧹 Stopping app container..."
    docker compose -f $COMPOSE_FILE stop $APP_CONTAINER || true
fi

# 4. Set production environment
echo "⚙️ Setting environment to production..."
chmod +x ./scripts/production.sh
./scripts/production.sh

# 5. Build container if code changed
if [ "$CODE_CHANGED" = true ]; then
    echo "🐳 Building app container..."
    docker compose -f $COMPOSE_FILE build --pull $APP_CONTAINER
fi

# 6. Start containers (idempotent)
echo "🐳 Starting containers..."
docker compose -f $COMPOSE_FILE up -d

# 7. Install dependencies (only if code changed)
if [ "$CODE_CHANGED" = true ]; then
    echo "📦 Installing dependencies inside container..."
    docker compose -f $COMPOSE_FILE run --rm $APP_CONTAINER bash -c "composer install --optimize-autoloader --no-dev"
fi

# 8. Clear & rebuild caches
echo "🧹 Optimizing application..."
docker compose -f $COMPOSE_FILE run --rm $APP_CONTAINER bash -c "php artisan optimize:clear && php artisan optimize"

# 9. Run migrations
echo "🗄️ Running database migrations..."
docker compose -f $COMPOSE_FILE run --rm $APP_CONTAINER bash -c "php artisan migrate --force"

# 10. Restart Horizon
echo "⚡ Restarting Horizon..."
docker compose -f $COMPOSE_FILE run --rm $APP_CONTAINER bash -c "php artisan horizon:terminate || true"

# 11. Restart Swagger UI container to reflect new API docs
echo "📜 Restarting Swagger UI container..."
docker compose -f $COMPOSE_FILE up -d --force-recreate swagger

echo "✅ Deployment complete!"
