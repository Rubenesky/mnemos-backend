#!/bin/bash
set -e

echo ""
echo "  ╔══════════════════════════════════════╗"
echo "  ║    Mnemos — Installation Wizard      ║"
echo "  ║  Open memory for organizations       ║"
echo "  ╚══════════════════════════════════════╝"
echo ""

# Check Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed."
    echo "   Please install Docker Desktop from: https://www.docker.com/products/docker-desktop"
    exit 1
fi

if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker Desktop and try again."
    exit 1
fi

echo "✅ Docker found."
echo ""

# Create .env from .env.example
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ Created .env configuration file."
fi

# Generate APP_KEY before starting containers
APP_KEY=$(docker run --rm php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));")
sed -i "s|APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
echo "✅ Application key generated."

# Collect required values
echo "─────────────────────────────────────────────"
echo "  Step 1 of 2: Cloudinary (file storage)"
echo "─────────────────────────────────────────────"
echo "  Sign up free at: https://cloudinary.com"
echo ""
read -p "  Enter your Cloudinary URL (cloudinary://...): " CLOUDINARY_URL
if [ -n "$CLOUDINARY_URL" ]; then
    sed -i "s|CLOUDINARY_URL=.*|CLOUDINARY_URL=${CLOUDINARY_URL}|" .env
fi

echo ""
echo "─────────────────────────────────────────────"
echo "  Step 2 of 2: Google Gemini AI (optional)"
echo "─────────────────────────────────────────────"
echo "  Get a free key at: https://aistudio.google.com/app/apikey"
echo ""
read -p "  Enter your Gemini API key (press Enter to skip): " GEMINI_KEY
if [ -n "$GEMINI_KEY" ]; then
    sed -i "s|GEMINI_API_KEY=.*|GEMINI_API_KEY=${GEMINI_KEY}|" .env
fi

echo ""
echo "🚀 Starting Mnemos..."
echo ""

# Build and start containers
docker compose up -d --build

# Wait for DB and run setup
echo "⏳ Waiting for database to be ready..."
RETRIES=30
until docker compose exec db mysqladmin ping -h localhost --silent 2>/dev/null; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then
        echo "❌ Database did not start in time. Check: docker compose logs db"
        exit 1
    fi
    sleep 2
done
echo "✅ Database is ready."

docker compose exec app php artisan migrate --force
echo "🌱 Seeding initial data..."
docker compose exec app php artisan db:seed --force || echo "   (No seeders found — skipping)"

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║    ✨ Mnemos is ready!                   ║"
echo "║                                          ║"
echo "║    Open in your browser:                 ║"
echo "║    http://localhost:8000                 ║"
echo "║                                          ║"
echo "║    Default admin login:                  ║"
echo "║    Email:    admin@example.com           ║"
echo "║    Password: [set during installation]   ║"
echo "╚══════════════════════════════════════════╝"
echo ""
