#!/bin/sh
set -e

# ─── AgileTracker Docker Entrypoint ─────────────────────────────────────────
# Handles first-run setup: .env, APP_KEY, DB, and migrations.

# If .env doesn't exist, create from example
if [ ! -f .env ]; then
    echo "→ Creating .env from .env.example..."
    cp .env.example .env

    # Set DB_CONNECTION to sqlite explicitly
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env

    # Set log to stderr so it appears in docker logs
    sed -i 's/^LOG_CHANNEL=.*/LOG_CHANNEL=stderr/' .env
fi

# Generate APP_KEY if not set
if ! grep -q '^APP_KEY=base64' .env 2>/dev/null; then
    echo "→ Generating APP_KEY..."
    php artisan key:generate --force
fi

# Create SQLite database file if it doesn't exist
if [ ! -f database/database.sqlite ]; then
    echo "→ Creating SQLite database..."
    touch database/database.sqlite
fi

# Run migrations (if not already run)
if [ ! -f database/.migrated ]; then
    echo "→ Running migrations..."
    php artisan migrate --force
    touch database/.migrated
fi

# Seed the database if no users exist
if ! php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | grep -q '[1-9]'; then
    echo "→ Seeding database..."
    php artisan db:seed --force
    echo "   ✓ Default admin created: admin@example.com / password"
fi

# Cache config & routes for better performance
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

echo "→ Starting AgileTracker on http://0.0.0.0:8000"
echo ""

# Execute the main command (php artisan serve)
exec "$@"