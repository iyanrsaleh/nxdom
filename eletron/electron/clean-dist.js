'use strict';
/**
 * Hapus folder dist/ — dengan beberapa percobaan untuk Windows (EBUSY / EPERM
 * bila app.asar atau exe masih dipegang Elektron, Explorer, atau antivirus).
 */
const fs = require('fs');
const path = require('path');

const dist = path.join(__dirname, '..', 'dist');
const nodeMajor = parseInt(process.versions.node.split('.')[0], 10);
const rmOpts =
  nodeMajor >= 18
    ? { recursive: true, force: true, maxRetries: 8, retryDelay: 200 }
    : { recursive: true, force: true };

function sleepMs(ms) {
  try {
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
  } catch {
    const end = Date.now() + ms;
    while (Date.now() < end) {
      /* tunggu singkat bila Atomics tidak dipakai */
    }
  }
}

const maxOuter = 20;
for (let attempt = 0; attempt < maxOuter; attempt++) {
  try {
    fs.rmSync(dist, rmOpts);
    process.exit(0);
  } catch (e) {
    if (e && e.code === 'ENOENT') {
      process.exit(0);
    }
    const retry =
      e &&
      (e.code === 'EBUSY' ||
        e.code === 'EPERM' ||
        e.code === 'ENOTEMPTY' ||
        e.code === 'EMFILE');
    if (retry && attempt < maxOuter - 1) {
      sleepMs(250 + attempt * 75);
      continue;
    }
    console.error(e.message);
    console.error(
      '\nFolder dist masih terkunci. Tutup NexaEletron / proses Electron, jendela Explorer yang membuka dist/, lalu jalankan lagi: npm run clean'
    );
    process.exit(1);
  }
}
