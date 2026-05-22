# Setup modul (`assets/modules/setup`)

Folder ini dipakai sebagai **titik dokumentasi dan berkas pendukung setup** terkait koleksi modul Nexa di `assets/modules/`.

## Tujuan

- Menyimpan catatan singkat tentang cara kerja registri komponen (`components.js`), demo (`index.html` per modul), dan konvensi penamaan berkas.
- Bisa diisi dengan skrip checklist, contoh path import, atau panduan onboarding pengembang—tanpa mengacaukan isi masing-masing folder modul.

## Konvensi modul (ringkas)

- Registri dan metadata komponen: `../components.js`.
- Indeks demo global: `../index.html` (daftar link ke demo per modul).
- Setiap modul biasanya punya `index.html` (demo) dan aset terkait di folder bernama modul itu sendiri.

## Path relatif

Dari aplikasi Electron / template workspace, modul biasanya direferensikan lewat path seperti:

`assets/modules/<NamaModul>/...`

Sesuaikan dengan root `file://` atau server statis yang dipakai proyek Anda.
