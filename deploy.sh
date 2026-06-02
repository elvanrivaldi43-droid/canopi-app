#!/bin/bash
# ============================================================
# deploy.sh — Pusat Kanopi | app.kanopibsd.co.id
# Jalankan di server Niagahoster via SSH:
#   bash deploy.sh
# ============================================================

set -e

APP_DIR="/home/u8221523/public_html/app"
REPO="https://github.com/elvanrivaldi43-droid/canopi-app.git"
PHP="php8.4"
COMPOSER="$APP_DIR/composer.phar"

echo "======================================"
echo "  Deploy Pusat Kanopi — $(date '+%Y-%m-%d %H:%M:%S')"
echo "======================================"

# ── 1. Clone atau Pull ─────────────────────────────────────
if [ -d "$APP_DIR/.git" ]; then
    echo "[1/8] Pulling latest code..."
    cd "$APP_DIR"
    git fetch origin main
    git reset --hard origin/main
else
    echo "[1/8] Cloning repository..."
    git clone "$REPO" "$APP_DIR"
    cd "$APP_DIR"
fi

# ── 2. Install / Update Composer ──────────────────────────
echo "[2/8] Installing Composer dependencies..."
if [ ! -f "$COMPOSER" ]; then
    curl -sS https://getcomposer.org/installer | $PHP -- --install-dir="$APP_DIR" --filename=composer.phar
fi
$PHP "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

# ── 3. Setup .env ─────────────────────────────────────────
echo "[3/8] Checking .env..."
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    echo "  .env dibuat dari .env.example — EDIT MANUAL sebelum lanjut!"
    echo "  nano $APP_DIR/.env"
    exit 1
fi

# ── 4. Generate key jika belum ada ────────────────────────
echo "[4/8] Generating app key (if needed)..."
KEY=$(grep "^APP_KEY=" "$APP_DIR/.env" | cut -d'=' -f2)
if [ -z "$KEY" ]; then
    $PHP "$APP_DIR/artisan" key:generate --force
fi

# ── 5. Migrate database ───────────────────────────────────
echo "[5/8] Running migrations..."
$PHP "$APP_DIR/artisan" migrate --force

# ── 6. Storage link ───────────────────────────────────────
echo "[6/8] Creating storage symlink..."
$PHP "$APP_DIR/artisan" storage:link --force 2>/dev/null || true

# ── 7. Optimize ───────────────────────────────────────────
echo "[7/8] Optimizing..."
$PHP "$APP_DIR/artisan" config:cache
$PHP "$APP_DIR/artisan" route:cache
$PHP "$APP_DIR/artisan" view:cache

# ── 8. Fix permissions ────────────────────────────────────
echo "[8/8] Setting permissions..."
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

echo ""
echo "======================================"
echo "  Deploy SELESAI! ✅"
echo "  Cek: https://app.kanopibsd.co.id"
echo "======================================"
