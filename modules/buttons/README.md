# Buttons

Referensi cepat komponen tombol Nexa/Primer.

**Path relatif:** `buttons`  
**Sumber demo lengkap:** `buttons/index.html`

## Class Inti

- `btn` (default)
- `btn btn-primary`
- `btn btn-outline`
- `btn btn-danger`
- `btn-link`
- `btn btn-invisible`
- `btn-sm`, `btn-large`, `btn-block`
- `btn-octicon`, `btn-octicon-danger`
- `close-button`
- `BtnGroup`, `BtnGroup-item`

## Utility

- `Counter`
- `dropdown-caret`
- `octicon ...`

## Snippet Utama

```html
<button class="btn" type="button">Batal</button>
<button class="btn btn-primary" type="button">Simpan</button>
<button class="btn btn-danger" type="button">Hapus</button>
```

```html
<button class="btn btn-outline" type="button">Outline</button>
<button class="btn btn-primary btn-sm" type="button">Small Primary</button>
<button class="btn btn-primary btn-large" type="button">Large Primary</button>
<button class="btn btn-primary btn-block" type="button">Block Primary</button>
```

```html
<button class="btn-octicon" type="button" aria-label="Edit">
  <span class="octicon octicon-pencil-16"></span>
</button>
```

```html
<div class="BtnGroup">
  <button class="btn BtnGroup-item" type="button">Prev</button>
  <button class="btn BtnGroup-item selected" type="button">Now</button>
  <button class="btn BtnGroup-item" type="button">Next</button>
</div>
```

## Aturan Singkat

- Gunakan `type="button"` untuk aksi non-submit.
- Gunakan `aria-label` untuk tombol icon-only.
- Gunakan class standar di atas; hindari class custom jika belum diperlukan.
