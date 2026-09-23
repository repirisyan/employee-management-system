# Sistem Manajemen Kepegawaian & Presensi (Employee Management System)

Aplikasi manajemen data kepegawaian dan sistem presensi (absensi) berbasis lokasi (GPS) modern yang dibangun menggunakan **Laravel**, **Inertia.js v3**, **Svelte 5**, dan **Tailwind CSS v4**.

---

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Persyaratan Sistem (System Requirements)](#persyaratan-sistem-system-requirements)
- [Instalasi Lokal Menggunakan Laravel Herd](#instalasi-lokal-menggunakan-laravel-herd)
- [Instalasi di Lingkungan Production](#instalasi-di-lingkungan-production)
- [Akun Demo Pengujian](#akun-demo-pengujian)
- [Catatan Khusus Fitur GPS Presensi](#catatan-khusus-fitur-gps-presensi)
- [Perintah Maintenance & Troubleshooting](#perintah-maintenance--troubleshooting)

---

## Fitur Utama

- **Dashboard Kehadiran**: Check-in & Check-out berbasis koordinat GPS perangkat secara akurat.
- **Manajemen Pegawai**: Pengelolaan NIP, data profil, foto, bagian, sub-bagian, role, dan akun login.
- **Struktur Organisasi Bertingkat**: Master Bagian dan Sub-Bagian (Parent-Child) dengan relasi dinamis.
- **Pengaturan Jam Kerja Fleksibel**: Konfigurasi jam masuk, jam pulang, dan toleransi keterlambatan mandiri via admin.
- **Filter Data Profesional**: Multi-select filter untuk Bagian, Sub-Bagian, Role, dan Status Kehadiran, serta Date Range Picker dengan kalender visual Flatpickr.
- **Tampilan Antarmuka Enterprise**: Mendukung Light Mode dan Dark Mode dengan palet warna enterprise (Slate & Royal Blue).
- **Keamanan Akun**: Dukungan Otentikasi Fortify, Two-Factor Authentication (2FA), dan Passkey (WebAuthn).
- **Keamanan Lingkungan Production**: Kredensial demo / akses cepat pengujian otomatis disembunyikan jika `APP_ENV=production`.

---

## Persyaratan Sistem (System Requirements)

### 1. Server / Runtime Environment

- **PHP**: Versi `^8.3` (Disarankan PHP 8.3, 8.4, atau 8.5)
- **Node.js**: Versi `>= 20.x` (Disarankan Node.js 20 LTS atau 22 LTS)
- **Package Manager**:
    - `composer` versi `>= 2.x`
    - `npm` versi `>= 10.x`

### 2. Ekstensi PHP yang Wajib Aktif

Pastikan ekstensi PHP berikut telah terpasang dan aktif di file `php.ini`:

- `pdo_mysql` _(Wajib untuk koneksi database MySQL/MariaDB)_
- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `filter`
- `gd` atau `imagick` _(diperlukan untuk upload & crop foto pegawai)_
- `hash`
- `json`
- `libxml`
- `mbstring`
- `openssl`
- `pcre`
- `pdo`
- `session`
- `tokenizer`
- `xml`

### 3. Database

- **MySQL** versi `>= 8.0` (Sangat Disarankan) atau **MariaDB** versi `>= 10.4`
- Karakter set: `utf8mb4` dengan collation `utf8mb4_unicode_ci`

### 4. Protokol Keamanan

- **HTTPS / SSL**: **Wajib** untuk fitur presensi GPS (HTML5 Geolocation API memerlukan _Secure Context_ / HTTPS di luar localhost).

---

## Instalasi Lokal Menggunakan Laravel Herd & MySQL (macOS & Windows)

[Laravel Herd](https://herd.laravel.com/) adalah cara termudah dan tercepat untuk menjalankan aplikasi ini di **macOS** maupun **Windows** secara native tanpa memerlukan Docker atau WSL.

### Langkah 1: Kloning Repositori

Tempatkan folder proyek di direktori yang dipantau oleh Herd:

- **macOS**: `~/Herd/EmployeeManagementSystem`
- **Windows**: `C:\Users\<NamaUser>\Herd\EmployeeManagementSystem` (atau `%USERPROFILE%\Herd\EmployeeManagementSystem`)

Buka terminal (macOS Terminal, Windows PowerShell, Git Bash, atau Windows Terminal):

```bash
# macOS / Linux / Git Bash
cd ~/Herd
git clone <URL_REPOSITORY> EmployeeManagementSystem
cd EmployeeManagementSystem

# Windows (PowerShell / Command Prompt)
cd $env:USERPROFILE\Herd
git clone <URL_REPOSITORY> EmployeeManagementSystem
cd EmployeeManagementSystem
```

Herd akan secara otomatis mendeteksi dan memetakan domain lokal:
`http://employeemanagementsystem.test`

### Langkah 2: Aktifkan SSL pada Domain Lokal (Wajib untuk GPS)

Agar peramban (browser seperti Chrome, Edge, Firefox) mengizinkan akses Geolocation / GPS saat presensi, domain lokal harus diamankan dengan HTTPS:

```bash
herd secure employeemanagementsystem
```

Domain lokal kini dapat diakses secara aman melalui: **`https://employeemanagementsystem.test`**

### Langkah 3: Siapkan Database MySQL

Pastikan service MySQL Anda sudah berjalan (melalui **Herd Pro Services**, **Laragon**, **XAMPP**, **DBngin**, atau MySQL Server bawaan Windows).

Buat database baru bernama `employeemanagementsystem`:

```bash
# Melalui terminal MySQL CLI:
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS employeemanagementsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Atau gunakan GUI database client di Windows/Mac (HeidiSQL, TablePlus, DBeaver, phpMyAdmin, Navicat).
```

### Langkah 4: Konfigurasi File Environment

Salin file konfigurasi `.env.example` ke `.env`:

```bash
# macOS / Linux / Git Bash / PowerShell
cp .env.example .env

# Windows Command Prompt (CMD)
copy .env.example .env
```

Buka file `.env`, pastikan konfigurasi MySQL dan URL aplikasi sudah sesuai:

```env
APP_NAME="Sistem Manajemen Kepegawaian"
APP_ENV=local
APP_DEBUG=true
APP_URL=https://employeemanagementsystem.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=employeemanagementsystem
DB_USERNAME=root
DB_PASSWORD=
```

_(Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` dengan kredensial MySQL lokal Anda jika menggunakan password)._

### Langkah 5: Install Dependensi PHP & JavaScript

```bash
# Install PHP packages
composer install

# Install JavaScript/Node.js packages
npm install
```

### Langkah 6: Generate Application Key & Storage Link

```bash
php artisan key:generate
php artisan storage:link
```

### Langkah 7: Jalankan Migrasi & Seeder Database MySQL

Jalankan migrasi tabel ke database MySQL beserta data demo pegawai, bagian, sub-bagian, role, dan presensi:

```bash
php artisan migrate --seed
```

> [!TIP]
> Jika database `employeemanagementsystem` belum sempat dibuat manual, saat menjalankan `php artisan migrate`, Laravel akan menanyakan:
> _"The database 'employeemanagementsystem' does not exist on the 'mysql' connection. Would you like to create it?"_
> Anda cukup mengetik **`yes`** dan menekan Enter.

### Langkah 8: Jalankan Frontend Build / Dev Server

Untuk pengembangan dengan Hot Module Replacement (HMR):

```bash
npm run dev
```

Atau jika ingin mengompilasi aset secara statis:

```bash
npm run build
```

Buka peramban di **`https://employeemanagementsystem.test`**.

---

## Instalasi di Lingkungan Production

Panduan berikut mengasumsikan server Linux (Ubuntu/Debian) dengan Nginx, PHP-FPM 8.3+, dan MySQL.

### Langkah 1: Persiapan Server & Kloning Proyek

```bash
cd /var/www
git clone <URL_REPOSITORY> employeemanagementsystem
cd /var/www/employeemanagementsystem
```

### Langkah 2: Hak Akses Folder (Permissions)

Berikan hak kepemilikan folder ke user web server (`www-data`):

```bash
sudo chown -R www-data:www-data /var/www/employeemanagementsystem
sudo chmod -R 775 /var/www/employeemanagementsystem/storage
sudo chmod -R 775 /var/www/employeemanagementsystem/bootstrap/cache
```

### Langkah 3: Konfigurasi Environment Production

Salin file `.env`:

```bash
cp .env.example .env
```

Buka `.env` dan konfigurasikan parameter production dengan tepat:

```env
APP_NAME="Portal Kepegawaian"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://presensi.domainanda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_db_production
DB_USERNAME=user_db_production
DB_PASSWORD=password_db_sangat_rahasia

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

> [!NOTE]
> Pada saat `APP_ENV=production`, tombol "Akses Cepat Pengujian" di halaman login akan **otomatis dihilangkan** demi keamanan.

### Langkah 4: Install Dependensi Tanpa Dev Packages

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### Langkah 5: Generate Key, Symlink, & Migrasi Database

```bash
php artisan key:generate --force
php artisan storage:link
php artisan migrate --force

# (Opsional) Jalankan seeder jika ini instalasi awal:
# php artisan db:seed --force
```

### Langkah 6: Optimasi Cache Laravel Production

Jalankan perintah optimasi untuk performa maksimal:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Langkah 7: Konfigurasi Virtual Host Nginx

Buat file konfigurasi Nginx, misalnya `/etc/nginx/sites-available/employeemanagementsystem`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name presensi.domainanda.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name presensi.domainanda.com;
    root /var/www/employeemanagementsystem/public;

    # SSL Certificate (dibuat oleh Certbot Let's Encrypt)
    # ssl_certificate /etc/letsencrypt/live/presensi.domainanda.com/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/presensi.domainanda.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Permissions-Policy "geolocation=(self)";

    index index.php;

    charset utf-8;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
```

Aktifkan konfigurasi dan reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/employeemanagementsystem /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Langkah 8: Pasang SSL Gratis (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d presensi.domainanda.com
```

### Langkah 9: Konfigurasi Background Task (Cron Job / Task Scheduling)

Buka crontab server:

```bash
crontab -e
```

Tambahkan baris berikut agar Laravel Scheduler berjalan otomatis setiap menit:

```cron
* * * * * cd /var/www/employeemanagementsystem && php artisan schedule:run >> /dev/null 2>&1
```

---

## Akun Demo Pengujian

Jika Anda menjalankan migrasi dengan flag `--seed` (`php artisan migrate --seed`), akun-akun demo berikut akan tersedia:

| Peran (Role)           | Email               | Password Default | Keterangan                           |
| ---------------------- | ------------------- | ---------------- | ------------------------------------ |
| **Super Admin**        | `admin@example.com` | `password`       | Hak akses penuh seluruh modul sistem |
| **Admin Bagian**       | `ahmad@example.com` | `password`       | Pengelola Bagian SDM                 |
| **Pegawai (Staf TI)**  | `budi@example.com`  | `password`       | Akses presensi harian staf TI        |
| **Pegawai (Keuangan)** | `siti@example.com`  | `password`       | Akses presensi staf Keuangan         |
| **Pegawai (Umum)**     | `dewi@example.com`  | `password`       | Akses presensi staf Bagian Umum      |

> [!WARNING]
> Segera ganti seluruh password default akun di atas setelah deployment production selesai!

---

## Catatan Khusus Fitur GPS Presensi

1. **Persyaratan HTTPS**:
   Fitur check-in dan check-out menggunakan API peramban `navigator.geolocation.getCurrentPosition()`. Standar keamanan browser modern (Chrome, Safari, Edge, Firefox) secara mutlak menolak izin lokasi jika website diakses via HTTP biasa.
2. **Pengujian Lokal**:
    - Jika menggunakan **Laravel Herd**, jalankan perintah `herd secure <nama-folder>` agar aplikasi berjalan di `https://...test`.
    - Atau akses via `http://localhost:8000` (browser mengecualikan `localhost` dari aturan HTTPS).
3. **Izin Lokasi di Perangkat**:
   Pastikan pengguna mengklik tombol **"Izinkan" (Allow)** saat peramban meminta izin akses lokasi pada pop-up pertama kali.

---

## Perintah Maintenance & Troubleshooting

### Mengaktifkan Mode Pemeliharaan (Maintenance Mode)

```bash
# Aktifkan maintenance mode saat update kode
php artisan down --secret="kunci-rahasia-admin"

# Nonaktifkan kembali setelah selesai update
php artisan up
```

### Membersihkan Cache

Jika terjadi perubahan konfigurasi atau pembaruan kode di server:

```bash
php artisan optimize:clear
php artisan optimize
```

### Memeriksa Status Log Aplikasi

```bash
tail -f storage/logs/laravel.log
```

### Menjalankan Test Suite (Pest)

```bash
vendor/bin/pest
```
