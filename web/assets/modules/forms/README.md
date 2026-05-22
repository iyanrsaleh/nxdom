# Forms

Versi singkat penggunaan form di Nexa.

**Path relatif:** `forms`  
**Alias utama di `Nexa.js`:** `NXUI.Form` (alias `NXUI.NexaForm`)  
**Orkestrator screen:** `NXUI.forms(screen)` (via `NexaFormsScreen`)

## Pakai Versi Singkat (disarankan)

```js
await NXUI.Form({
  id: "myForm",
  onclick: {
    submitid: "#submit-user",
    endpoint: "/api/user",
    validation: {
      nama: { required: true, min: 3 },
    },
  },
});
```

## Untuk Render Screen Forms

```js
await NXUI.forms({
  data: {
    id: "app_or_bucket_id",
  },
});
```

## Catatan Penting

- Gunakan `NXUI.Form` (bukan langsung `Validation(...)`) agar flow renderer + validasi tetap standar.
- `submitid` harus menunjuk tombol submit yang valid.
- Untuk mode update/preload, gunakan `recordId` (bukan `id`).

