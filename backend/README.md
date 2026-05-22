# Nexa Dom Framework

Platform aplikasi PHP (Nexa Core). Proyek ini dapat di-clone manual atau di-install sebagai kerangka baru lewat Composer.

## Prasyarat

- **PHP 8** (wajib)
- **Composer** - https://getcomposer.org/download/
- **Git** (disarankan, untuk version control — pakai perintah `git` langsung)
- **Node.js** (opsional, untuk server Node.js) - https://nodejs.org/

## Instalasi proyek baru dengan Composer

```bash
composer create-project nexadom/framework nama-folder-proyek
```

Contoh:

```bash
composer create-project nexadom/framework nexadom
cd nexadom
```

Untuk folder kosong saat ini (setelah `cd` ke direktori tujuan):

```bash
composer create-project nexadom/framework .
```

Paket `nexadom/framework` harus ada di Packagist atau repositori Composer Anda.

## CLI nxdom (terminal)

Di **Windows** gunakan `nxdom.bat` (dipanggil sebagai `.\nxdom`) bersama PowerShell; di **Linux** dan **macOS** gunakan skrip Bash `nxdom` di root proyek (tanpa `.bat`). Keduanya memanggil PHP yang sama untuk `make`, `migrate`, dan **`start`** (server pengembangan).

### Windows (PowerShell)

Jalankan **sekali tanpa argumen** di root proyek agar `nxdom-setup.ps1` jalan (PATH, Git, fungsi `nxdom` di profil PowerShell):

```powershell
cd C:\path\ke\root-proyek
.\nxdom
```

### Linux / macOS (bash / zsh)

Skrip `nxdom` ada di root repo. Beri izin eksekusi sekali, lalu panggil dengan `./`. Pastikan **PHP** dan **git** ada di `PATH`.

```bash
cd /path/ke/root-proyek
chmod +x nxdom
./nxdom
```

Contoh perintah: `./nxdom migrate run`, `./nxdom make 1/Product`, `./nxdom start`. Opsional: tambahkan folder proyek ke `PATH` di `~/.bashrc` atau `~/.zshrc` agar bisa mengetik `nxdom` tanpa `./`.

### Penjelasan: titik, spasi, lalu $PROFILE (Windows)

Setelah setup, fungsi **nxdom** ditulis ke **profil PowerShell** (skrip yang dibaca saat terminal baru dibuka).

- **$PROFILE** â€” variabel berisi path file profil Anda (contoh path: Documents/PowerShell/Microsoft.PowerShell_profile.ps1).
- **Titik di depan** â€” itu perintah *dot-source*: jalankan file profil di sesi terminal ini, supaya fungsi nxdom langsung aktif tanpa tutup buka terminal.

Contoh (satu baris perintah pertama adalah titik + spasi + $PROFILE):

```powershell
. $PROFILE
nxdom make 1/Product
```

Jika Anda belum memuat profil, di terminal lama perintah **nxdom** bisa belum ada sampai tab baru dibuka atau Anda menjalankan contoh di atas. Tanpa itu, tetap bisa pakai **.\nxdom** dari folder root proyek.

**Alternatif:** panggil **.\nxdom** dari root proyek tanpa mengandalkan fungsi di profil.

**Linux / macOS:** tidak ada `$PROFILE` PowerShell; jalankan `./nxdom` dari root atau pastikan direktori proyek ada di `PATH`.

### nxdom make (generator controller)

- Admin: `nxdom make 1/Product` atau `nxdom make Admin/User`
- Api: prefiks `2/...`, Frontend: prefiks `3/...`
- Tanpa argumen: mode interaktif

### nxdom migrate

```powershell
nxdom migrate run
nxdom migrate rollback
nxdom migrate status
nxdom migrate create CreateProductsTable
nxdom migrate createdb nama_database
```

Perintah **createdb** membuat database MySQL (`CREATE DATABASE IF NOT EXISTS`) memakai kredensial `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT` dari `.env`, lalu memperbarui baris **`DB_DATABASE`** di `.env` ke nama yang Anda berikan.

Tanpa argumen: menu interaktif (termasuk opsi **5** = createdb; Anda akan diminta nama database).

### nxdom start

Menjalankan **PHP built-in server** dengan **`system/bin/router.php`** (MIME statis, routing ke framework; cwd = root proyek).

- **Windows:** memanggil **`system/bin/start-server.bat`** (cek `vendor`, pilihan port, localhost vs jaringan). Contoh: `nxdom start` atau `nxdom start 3000`.
- **Linux / macOS:** `php -S localhost:PORT system/bin/router.php` â€” port default **8000** jika tidak ada argumen. Contoh: `./nxdom start` atau `./nxdom start 3000`.

Hentikan server dengan **Ctrl+C**.

### nxdom install node (NEW!)

Setup Node.js server untuk pertama kali. Perintah ini akan:
- Membuat file `server.js` (Node.js server dengan proxy ke PHP)
- Membuat file `package.json` (dependencies configuration)
- Menginstall semua dependencies ke `node_modules/`

```powershell
# Windows
nxdom install node

# Linux / macOS
./nxdom install node
```

**Catatan:** Pastikan Node.js sudah terinstall. Download dari https://nodejs.org/

### nxdom uninstall node (NEW!)

Menghapus Node.js server dan semua file terkait:
- Menghentikan Node.js server yang sedang berjalan
- Menghapus `server.js`
- Menghapus `package.json` dan `package-lock.json`
- Menghapus folder `node_modules/`

```powershell
# Windows
nxdom uninstall node

# Linux / macOS
./nxdom uninstall node
```

**Keamanan:** 
- ⚠️ Perintah ini **hanya dapat dijalankan di mode DEVELOPMENT**
- Cek file `.env`: `APP_ENV=development`
- Jika `APP_ENV=production`, uninstall akan ditolak untuk mencegah penghapusan tidak sengaja
- Anda akan diminta konfirmasi sebelum menghapus

### nxdom node (NEW!)

Menjalankan **Node.js server** dengan Express untuk API endpoints dan microservices.

**Development Mode:**
```powershell
# Windows
nxdom node          # Port default 3000
nxdom node 4000     # Custom port

# Linux / macOS
./nxdom node        # Port default 3000
./nxdom node 4000   # Custom port
```

**Production Mode (PM2):**
```powershell
# Windows
nxdom node production

# Linux / macOS
./nxdom node production
```

Perintah `nxdom node production` akan:
- Membuat file `ecosystem.config.js` (jika belum ada)
- Menjalankan server dengan PM2 (cluster mode, 2 instances)
- Auto-restart jika crash
- Load balancing otomatis

**Fitur Proxy API:**
Node.js server juga berfungsi sebagai proxy untuk PHP API controllers. Request ke `/nx/*` akan diteruskan ke PHP server sebagai `/api/*`. Lihat [API_PROXY.md](API_PROXY.md) untuk dokumentasi lengkap.

**Production Deployment:**
Untuk server online/production, gunakan PM2 process manager. Lihat [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md) untuk panduan lengkap.

### nxdom restart (NEW!)

Restart server PHP, Node.js, atau keduanya.

```powershell
# Windows
nxdom restart php    # Restart PHP server saja
nxdom restart node   # Restart Node.js server saja
nxdom restart both   # Restart kedua server

# Linux / macOS
./nxdom restart php
./nxdom restart node
./nxdom restart both
```

## Ringkasan cepat

### Windows (PowerShell)

| Kebutuhan | Perintah |
| --- | --- |
| Proyek baru | composer create-project nexadom/framework folder |
| Setup + fungsi nxdom | .\nxdom (di root proyek) |
| Muat profil di sesi ini | titik spasi $PROFILE, lalu nxdom ... |
| Controller | nxdom make 1/Nama |
| Migrasi | nxdom migrate run |
| Buat DB + set `DB_DATABASE` di `.env` | nxdom migrate createdb nama_db |
| Server dev (PHP + system/bin/router.php) | nxdom start atau nxdom start 3000 |
| Setup Node.js (pertama kali) | nxdom install node |
| Hapus Node.js | nxdom uninstall node |
| Server Node.js (development) | nxdom node atau nxdom node 4000 |
| Server Node.js (production/PM2) | nxdom node production |
| Restart server | nxdom restart both |

### Linux / macOS (dari root proyek)

| Kebutuhan | Perintah |
| --- | --- |
| Izinkan skrip nxdom | chmod +x nxdom |
| Bantuan / cek CLI | ./nxdom |
| Controller | ./nxdom make 1/Nama |
| Migrasi | ./nxdom migrate run |
| Buat DB + set `DB_DATABASE` di `.env` | ./nxdom migrate createdb nama_db |
| Server dev (PHP + system/bin/router.php) | ./nxdom start atau ./nxdom start 3000 |
| Setup Node.js (pertama kali) | ./nxdom install node |
| Hapus Node.js | ./nxdom uninstall node |
| Server Node.js (development) | ./nxdom node atau ./nxdom node 4000 |
| Server Node.js (production/PM2) | ./nxdom node production |
| Restart server | ./nxdom restart both |

**Windows:** jalankan `nxdom` tanpa argumen untuk bantuan singkat. **Linux / macOS:** jalankan `./nxdom` tanpa argumen. Pastikan PHP tersedia di `PATH`.