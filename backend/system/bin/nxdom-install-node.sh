#!/usr/bin/env bash
# NexaUI - Node.js Server Installation Script
# Usage: ./nxdom install node

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$SCRIPT_DIR" || exit 1

echo ""
echo "  ╔═══════════════════════════════════════════╗"
echo "  ║   NexaUI - Node.js Server Installation   ║"
echo "  ╚═══════════════════════════════════════════╝"
echo ""

# Check if Node.js is installed
if ! command -v node >/dev/null 2>&1; then
    echo "  [ERROR] Node.js tidak ditemukan!" >&2
    echo "" >&2
    echo "  Silakan install Node.js terlebih dahulu:" >&2
    echo "  Download: https://nodejs.org/" >&2
    echo "" >&2
    echo "  Atau install via package manager:" >&2
    echo "    Ubuntu/Debian: sudo apt install nodejs npm" >&2
    echo "    macOS: brew install node" >&2
    echo "" >&2
    echo "  Setelah install, jalankan lagi:" >&2
    echo "    ./nxdom install node" >&2
    echo "" >&2
    exit 1
fi

echo "  [1/4] Checking Node.js installation..."
node --version
npm --version
echo ""

# Check if files already exist
FILES_EXIST=0
if [[ -f "server.js" ]] || [[ -f "package.json" ]]; then
    FILES_EXIST=1
fi

if [[ $FILES_EXIST -eq 1 ]]; then
    echo "  [WARNING] File server.js atau package.json sudah ada!"
    echo ""
    read -r -p "  Apakah Anda ingin menimpa file yang ada? (y/n): " OVERWRITE
    if [[ ! "$OVERWRITE" =~ ^[Yy]$ ]]; then
        echo ""
        echo "  [CANCELLED] Instalasi dibatalkan."
        echo ""
        exit 0
    fi
    echo ""
fi

echo "  [2/4] Creating package.json..."
cp "$SCRIPT_DIR/system/bin/templates/package.json.template" "package.json" 2>/dev/null
if [[ $? -ne 0 ]]; then
    # Fallback to heredoc if template not found
cat > package.json << 'EOF'
{
  "name": "nexaui",
  "version": "1.0.0",
  "description": "NexaUI Framework with Node.js server support",
  "main": "server.js",
  "scripts": {
    "start": "node server.js",
    "dev": "nodemon server.js"
  },
  "keywords": ["nexaui", "framework", "php", "nodejs"],
  "author": "",
  "license": "MIT",
  "dependencies": {
    "express": "^4.18.2",
    "http-proxy-middleware": "^2.0.6",
    "mysql2": "^3.6.0",
    "dotenv": "^16.3.1",
    "cors": "^2.8.5",
    "helmet": "^7.1.0",
    "morgan": "^1.10.0"
  },
  "devDependencies": {
    "nodemon": "^3.0.1"
  }
}
EOF
fi
echo "  [OK] package.json created"
echo ""

echo "  [3/4] Creating server.js..."
cp "$SCRIPT_DIR/system/bin/templates/server.js.template" "server.js" 2>/dev/null
if [[ $? -ne 0 ]]; then
    # Fallback to heredoc if template not found
cat > server.js << 'EOF'
const express = require('express');
const path = require('path');
const { createProxyMiddleware } = require('http-proxy-middleware');
const app = express();

// Port configuration
const PORT = process.env.PORT || 3000;
const PHP_SERVER = process.env.PHP_SERVER || 'http://localhost'; // Apache default port 80

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Proxy untuk PHP API Controllers (NexaUI)
// Semua request ke /nx/* akan diteruskan ke PHP server
app.use('/nx', createProxyMiddleware({
  target: PHP_SERVER,
  changeOrigin: true,
  pathRewrite: {
    '^/nx': '/api' // Remove /nx prefix, forward as /api
  },
  onProxyReq: (proxyReq, req, res) => {
    console.log(`[Proxy] ${req.method} ${req.url} -> ${PHP_SERVER}${req.url.replace('/nx', '/api')}`);
  },
  onError: (err, req, res) => {
    console.error('[Proxy Error]', err.message);
    res.status(500).json({
      error: 'PHP server proxy error',
      message: err.message,
      phpServer: PHP_SERVER
    });
  }
}));

// Node.js API Routes (native)
app.get('/api/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    message: 'NexaUI Node.js server is running',
    timestamp: new Date().toISOString(),
    phpServer: PHP_SERVER
  });
});

// Example API endpoint
app.get('/api/test', (req, res) => {
  res.json({ 
    message: 'Test endpoint working',
    data: {
      framework: 'NexaUI',
      server: 'Node.js + Express',
      phpProxy: `Use /nx/* to access PHP controllers`
    }
  });
});

// Start server
app.listen(PORT, () => {
  console.log('');
  console.log('  ╔═══════════════════════════════════════╗');
  console.log('  ║   NexaUI — Node.js Server Running    ║');
  console.log('  ╚═══════════════════════════════════════╝');
  console.log('');
  console.log(`  🚀 Server: http://localhost:${PORT}`);
  console.log(`  📊 Health: http://localhost:${PORT}/api/health`);
  console.log('');
  console.log('  Press Ctrl+C to stop');
  console.log('');
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('\n  Server shutting down gracefully...');
  process.exit(0);
});

process.on('SIGINT', () => {
  console.log('\n  Server shutting down gracefully...');
  process.exit(0);
});
EOF
fi
echo "  [OK] server.js created"
echo ""

echo "  [4/4] Installing Node.js dependencies..."
echo ""
npm install
if [[ $? -ne 0 ]]; then
    echo ""
    echo "  [ERROR] Gagal menginstall dependencies!" >&2
    echo "  Coba jalankan manual: npm install" >&2
    echo ""
    exit 1
fi

echo ""
echo "  ╔═══════════════════════════════════════════╗"
echo "  ║   Installation Complete! ✓                ║"
echo "  ╚═══════════════════════════════════════════╝"
echo ""
echo "  File yang dibuat:"
echo "    ✓ package.json"
echo "    ✓ server.js"
echo "    ✓ node_modules/ (dependencies)"
echo ""
echo "  Langkah selanjutnya:"
echo "    1. Jalankan Node.js server:"
echo "       ./nxdom node"
echo "       ./nxdom node 3000"
echo ""
echo "    2. Test server:"
echo "       http://localhost:3000/api/health"
echo ""
echo "    3. Akses PHP API via proxy:"
echo "       http://localhost:3000/nx/test"
echo ""
echo "  Dokumentasi lengkap: API_PROXY.md"
echo ""

exit 0
