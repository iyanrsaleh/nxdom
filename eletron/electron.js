'use strict';
/**
 * Entry Electron di akar proyek — memuat proses utama dari folder electron/ (dev)
 * atau resources/electron/ (installer). Isi aplikasi di app.asar (Express di index.js lewat require).
 */
const path = require('path');
const fs = require('fs');
const { app } = require('electron');

/**
 * Mode unpackaged: pakai userData di dalam repo (bukan %APPDATA% default).
 * Tanpa ini, dua instance Electron dengan appId sama (Nexa + npm run dev) bentrok:
 * cache/quota DB terkunci → error "Unable to move the cache" dan tampilan tidak ikut reload.
 */
if (!app.isPackaged) {
  const devUserData = path.join(__dirname, '.electron-userdata-dev');
  try {
    fs.mkdirSync(devUserData, { recursive: true });
  } catch (_) {
    /* abaikan */
  }
  app.setPath('userData', devUserData);
}

const extMainCandidates = app.isPackaged
  ? [
      // Build baru: electron/ ikut files dan berada di app.asar
      path.join(app.getAppPath(), 'electron', 'main.js'),
      // Build lama: electron/ disalin ke resources/electron (extraResources)
      path.join(process.resourcesPath, 'electron', 'main.js'),
    ]
  : [path.join(__dirname, 'electron', 'main.js')];
const extMain = extMainCandidates.find((p) => fs.existsSync(p)) || extMainCandidates[0];

if (!fs.existsSync(extMain)) {
  app.whenReady().then(() => {
    const { dialog } = require('electron');
    dialog.showErrorBox(
      'EletronNexa',
      'Berkas utama tidak ditemukan:\n' + extMain
    );
    app.quit();
  });
} else {
  require(extMain);
}

