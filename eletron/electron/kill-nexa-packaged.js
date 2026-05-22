'use strict';
/**
 * Hentikan NexaEletron yang jalan dari dist/ atau instalasi — agar electron-builder
 * bisa menghapus/menimpa app.asar (Windows: EBUSY / "used by another process").
 * Hanya mematikan image name NexaEletron.exe, bukan electron.exe (Cursor/VS Code).
 */
const { execSync } = require('child_process');

if (process.platform !== 'win32') {
  process.exit(0);
}

try {
  execSync('taskkill /F /IM NexaEletron.exe /T', { stdio: 'ignore' });
} catch {
  /* proses tidak ada */
}
