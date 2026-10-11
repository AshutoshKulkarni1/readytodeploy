#!/bin/sh
set -e

PORT="${PORT:-8000}"
echo "==> Starting Test Platform (Port: $PORT)..."

# Enable concurrent worker processes in PHP CLI server
export PHP_CLI_SERVER_WORKERS=4

# 1. Run database initialization
if [ -f "sql/init_db.php" ]; then
    echo "==> Checking database connection & migrations..."
    php sql/init_db.php || echo "==> Note: DB init skipped or not reachable."
fi

# 2. Start Python Analytics service in background if available
if [ -f "src/python/app.py" ]; then
    echo "==> Starting Python analytics service on 127.0.0.1:5000..."
    if command -v gunicorn >/dev/null 2>&1; then
        gunicorn -w 2 -b 127.0.0.1:5000 --chdir src/python app:app --daemon || (python3 src/python/app.py &)
    else
        python3 src/python/app.py &
    fi
fi

# 3. Start PHP web server
echo "==> Starting PHP web server on 0.0.0.0:$PORT..."
exec php -S "0.0.0.0:$PORT" -t src/php/public router.php
