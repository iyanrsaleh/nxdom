# Native Module (`NexaNativeScreen`)

Modul ini dipakai untuk merender form **native/in-line** langsung dari payload `app` yang dikirim caller, tanpa ketergantungan ke bucket `nexaStore` untuk pengambilan konfigurasi form.

## Tujuan

- Menampilkan form update berbasis konfigurasi `production.from`.
- Menggunakan handler `method` (mis. `nx.methoduser`) untuk proses submit.
- Menjaga alur tetap sederhana: caller kirim konfigurasi app, modul render form.

## Fungsi Utama

- `NexaNativeScreen.render(data)`
  - Entry point render layar native.
  - Mengambil source konfigurasi dari payload (`data`, `data.data`, `data.app`, `data.data.app`).
  - Menyiapkan form config, validasi, tombol aksi, lalu memanggil `NXUI.Form`.

- `resolveConfig(data)`
  - Menentukan `key`, `method`, `strictValidation`, `recordId`.
  - Menjalankan `buildQuery(data.production)` lalu query ke `Office` untuk mendapatkan data baris (`rowData`) saat mode update.

- `cloneFormFields(from)`
  - Menyalin field form dari `production.from` (hanya yang `forms === true`).

- `hydratemyFromFromRow(myFrom, row)`
  - Mengisi `myFrom[field].value` berdasarkan data baris query.
  - Contoh: `row.nama` -> `myFrom.nama.value`.

- `normalizeStandard(source, method, strictValidation, recordId)`
  - Menormalkan struktur payload agar kompatibel dengan renderer form.

## Alur Data Singkat

1. Caller memanggil `Screen.run("native")` dengan payload app.
2. `NexaNativeScreen` membaca konfigurasi `production.from`.
3. Jika query berhasil, `rowData` dipetakan ke `myFrom.value`.
4. `NXUI.Form` dirender dengan:
   - `setDataBy.form = myFrom`
   - `onclick.send = method` (handler global `nx.*` / `NXUI.*`)

## Struktur Payload Minimal

Contoh payload yang aman untuk mode native:

```js
{
  id: "app_id",
  appname: "user",
  method: "methoduser",
  strictValidation: true,
  recordId: 14,
  production: {
    from: {
      nama: { forms: true, name: "nama", type: "text", value: false },
      email: { forms: true, name: "email", type: "email", value: false }
    },
    setting: {
      forms: {
        sendTitle: "Update",
        methodById: "methoduser"
      }
    }
  }
}
```

## Integrasi dengan `updateAccount.js`

Pada implementasi saat ini:

- `updateAccount.js` membentuk payload `app`.
- `new NX.Screen({...app}).run("native")` memanggil renderer ini.
- Submit form diproses oleh `nx.methoduser`.
- Sinkronisasi nama profil (`oauth`, memory `NEXA`, dan UI sidebar) dilakukan di handler submit.

## Catatan Penting

- Modul ini ditujukan untuk alur native berbasis payload app.
- Jika payload tidak mengandung `production`, form tidak bisa dirender.
- Pastikan `method` benar-benar tersedia di global handler (`nx.methodX`).

