'use strict';
/**
 * Build dari dist bersih — urutan tetap di Windows (hindari masalah && di beberapa shell).
 * 1) Hentikan NexaEletron yang mengunci app.asar
 * 2) Hapus dist/
 * 3) electron-builder
 */
const { spawnSync } = require('child_process');
const path = require('path');

const root = path.join(__dirname, '..');

function runNode(scriptRelative, label) {
  console.log(`\n[build-fresh] ${label}\n`);
  const r = spawnSync(process.execPath, [path.join(root, scriptRelative)], {
    cwd: root,
    stdio: 'inherit',
    env: process.env,
  });
  if (r.status !== 0) {
    console.error(`\n[build-fresh] Gagal: ${label} (exit ${r.status ?? r.signal})\n`);
    process.exit(r.status === null ? 1 : r.status);
  }
}

function runElectronBuilder() {
  console.log('\n[build-fresh] electron-builder\n');
  const cli = path.join(root, 'node_modules', 'electron-builder', 'cli.js');
  const env = {
    ...process.env,
    CSC_IDENTITY_AUTO_DISCOVERY: 'false',
  };
  const r = spawnSync(process.execPath, [cli], {
    cwd: root,
    stdio: 'inherit',
    env,
  });
  if (r.status !== 0) {
    console.error(`\n[build-fresh] electron-builder gagal (exit ${r.status ?? r.signal})\n`);
    process.exit(r.status === null ? 1 : r.status);
  }
}

runNode(path.join('electron', 'kill-nexa-packaged.js'), 'Menghentikan NexaEletron (bila berjalan)');
runNode(path.join('electron', 'clean-dist.js'), 'Menghapus folder dist/');
runElectronBuilder();

console.log('\n[build-fresh] Selesai.\n');
