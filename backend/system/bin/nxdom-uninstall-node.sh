#!/usr/bin/env bash
# NexaUI - Node.js Server Uninstall Script
# Usage: ./nxdom uninstall node

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$SCRIPT_DIR" || exit 1

echo ""
echo "  ╔═══════════════════════════════════════════╗"
echo "  ║   NexaUI - Node.js Server Uninstall      ║"
echo "  ╚═══════════════════════════════════════════╝"
echo ""

# Check environment from .env file
APP_ENV="production"
if [[ -f ".env" ]]; then
    # Extract APP_ENV value, remove comments and trim spaces
    APP_ENV=$(grep -i "^APP_ENV" .env | cut -d'=' -f2 | cut -d'#' -f1 | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
    [[ -z "$APP_ENV" ]] && APP_ENV="production"
fi

# Only allow uninstall in development mode
if [[ "${APP_ENV,,}" != "development" ]]; then
    echo "  [ERROR] Uninstall hanya dapat dijalankan di mode DEVELOPMENT!" >&2
    echo "" >&2
    echo "  Environment saat ini: $APP_ENV" >&2
    echo "" >&2
    echo "  Untuk menjalankan uninstall:" >&2
    echo "    1. Buka file .env" >&2
    echo "    2. Ubah APP_ENV=production menjadi APP_ENV=development" >&2
    echo "    3. Jalankan lagi: ./nxdom uninstall node" >&2
    echo "" >&2
    echo "  [SECURITY] Ini untuk mencegah penghapusan tidak sengaja di production." >&2
    echo "" >&2
    exit 1
fi

echo "  [ENVIRONMENT] Mode: DEVELOPMENT ✓"
echo ""

# Check if Node.js files exist
FILES_EXIST=0
if [[ -f "server.js" ]] || [[ -f "package.json" ]] || [[ -d "node_modules" ]]; then
    FILES_EXIST=1
fi

if [[ $FILES_EXIST -eq 0 ]]; then
    echo "  [INFO] Node.js server belum terinstall."
    echo ""
    echo "  Tidak ada file yang perlu dihapus:"
    echo "    - server.js"
    echo "    - package.json"
    echo "    - node_modules/"
    echo ""
    echo "  Untuk install Node.js server:"
    echo "    ./nxdom install node"
    echo ""
    exit 0
fi

echo "  [WARNING] Perintah ini akan menghapus:"
echo ""
[[ -f "server.js" ]] && echo "    ✗ server.js"
[[ -f "package.json" ]] && echo "    ✗ package.json"
[[ -f "package-lock.json" ]] && echo "    ✗ package-lock.json"
[[ -d "node_modules" ]] && echo "    ✗ node_modules/ (folder dan semua isinya)"
[[ -f "ecosystem.config.js" ]] && echo "    ✗ ecosystem.config.js"
echo ""
read -r -p "  Apakah Anda yakin ingin menghapus? (y/n): " CONFIRM

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo ""
    echo "  [CANCELLED] Uninstall dibatalkan."
    echo ""
    exit 0
fi

echo ""
echo "  [1/5] Stopping Node.js server..."

# Stop PM2 process if exists
if command -v pm2 &>/dev/null; then
    pm2 stop nxdom-node &>/dev/null
    pm2 delete nxdom-node &>/dev/null
    if [[ $? -eq 0 ]]; then
        echo "  ✓ PM2 process stopped and deleted"
    fi
fi

# Stop regular Node.js processes
if pkill -f "node.*server.js" 2>/dev/null; then
    echo "  ✓ Node.js server stopped"
else
    echo "  ℹ No Node.js processes running"
fi
echo ""

echo "  [2/5] Removing server.js..."
if [[ -f "server.js" ]]; then
    rm -f "server.js"
    echo "  ✓ server.js deleted"
else
    echo "  ℹ server.js not found"
fi
echo ""

echo "  [3/5] Removing package files..."
if [[ -f "package.json" ]]; then
    rm -f "package.json"
    echo "  ✓ package.json deleted"
fi
if [[ -f "package-lock.json" ]]; then
    rm -f "package-lock.json"
    echo "  ✓ package-lock.json deleted"
fi
echo ""

echo "  [4/5] Removing node_modules..."
if [[ -d "node_modules" ]]; then
    echo "  ⏳ Deleting node_modules folder (this may take a moment)..."
    rm -rf "node_modules"
    if [[ $? -eq 0 ]]; then
        echo "  ✓ node_modules deleted"
    else
        echo "  ⚠ Warning: Failed to delete some files in node_modules"
        echo "  ℹ Try running: sudo rm -rf node_modules"
    fi
else
    echo "  ℹ node_modules not found"
fi
echo ""

echo "  [5/5] Removing PM2 configuration..."
if [[ -f "ecosystem.config.js" ]]; then
    rm -f "ecosystem.config.js"
    echo "  ✓ ecosystem.config.js deleted"
else
    echo "  ℹ ecosystem.config.js not found"
fi
echo ""

echo "  ╔═══════════════════════════════════════════╗"
echo "  ║   Uninstall Complete! ✓                   ║"
echo "  ╚═══════════════════════════════════════════╝"
echo ""
echo "  Node.js server telah dihapus dari project."
echo ""
echo "  Untuk install kembali:"
echo "    ./nxdom install node"
echo ""

exit 0
