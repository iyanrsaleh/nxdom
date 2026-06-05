# Example Extension

Template extension untuk Developer Extensions System.
Gunakan folder ini sebagai referensi saat membuat extension baru.

## Struktur File

```
example/
├── README.md      — dokumentasi extension (file ini)
├── package.json   — metadata: id, title, viewId, place, dll
├── index.js       — registrasi view (render & renderTab)
├── sidebar.js     — konten sidebar kiri
├── panel.js       — konten tab panel kanan
└── styles.css     — custom styles (opsional)
```

## Cara Membuat Extension Baru

1. Copy folder ini ke folder terpisah di repo Git Anda
2. Ubah `id`, `title`, `viewId`, dan `place` di `package.json`
3. Ubah `id` di `index.js` — harus sama dengan `viewId`
4. Edit `sidebar.js` dan `panel.js` sesuai kebutuhan
5. Install via Developer panel dengan paste URL repo

## Catatan

- `id` di `package.json` → kunci unik install state, format `dev_namaextension`
- `id` di `index.js` → harus sama dengan `viewId` di `package.json`
- `viewId` → harus sama dengan nama folder repo (tanpa prefix `developer/`)
- `place: "edge"` → tampil di panel kanan; `"activity-bar"` → tampil di sidebar kiri

Lihat `templates/developer/README.md` untuk dokumentasi lengkap.
