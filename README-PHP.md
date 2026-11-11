# SMARTATTEND AI — PHP Hosting Edition

Versi PHP dibina untuk hosting yang menyediakan PHP 8.1+ dan MySQL/MariaDB. Flask lama masih dikekalkan dalam repositori asal; versi hosting PHP bermula daripada `index.php` di root.

## Seni bina
- PHP + PDO: login, peranan admin/user, API, pengurusan pengguna dan rekod.
- MySQL: akaun, profil wajah dan kehadiran.
- face-api.js dalam browser: kamera, pengesanan muka, bounding box dan descriptor 128-dimensi.
- Kamera memerlukan HTTPS (atau localhost). Tiada akses webcam terus dari server hosting.

## Pemasangan
1. Cipta database MySQL, kemudian import `php/schema.sql` melalui phpMyAdmin.
2. Tetapkan environment variables pada hosting:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `DB_PORT` (pilihan, lalai 3306)
3. Upload semua fail dan folder projek ke document root hosting. Pastikan folder `php/` dan `assets/` turut dimuat naik.
4. Buka domain dan pilih **Persediaan akaun pentadbir pertama**. Cipta kata laluan sekurang-kurangnya 10 aksara.
5. Log masuk sebagai admin, cipta akaun pengguna di Pengurusan Pengguna.
6. Di Pendaftaran Wajah, pilih akaun pengguna yang sepadan, aktifkan kamera dan tangkap sampel wajah.
7. Buka Kamera AI untuk mengesan dan merekod kehadiran.

## Penting
- Muat turun model face-api.js dibuat daripada CDN jsDelivr (sambungan internet diperlukan pada pelayar).
- Untuk kamera, laman mesti menggunakan HTTPS. Browser akan meminta kebenaran kamera.
- Ambang padanan semasa ialah jarak Euclidean 0.50. Uji dengan kamera dan keadaan pencahayaan sebenar; nilai ini bukan jaminan ketepatan biometrik.
- Versi ini menggunakan satu descriptor purata daripada beberapa sampel untuk setiap profil. Daftar semula wajah dalam versi PHP; fail model LBPH lama tidak boleh digunakan terus.
- Lindungi akses phpMyAdmin dan database. Gunakan kata laluan database yang kuat, HTTPS, backup berkala dan polisi persetujuan/pemadaman data biometrik.
- Jangan letakkan fail rahsia konfigurasi dalam folder awam. Gunakan environment variables hosting.

## Had hosting percuma
Hosting PHP percuma biasanya boleh menjalankan PHP/MySQL tetapi sesetengah penyedia mengehadkan cron, sambungan luar atau saiz database. Face recognition berlaku pada peranti pengguna, jadi hosting tidak perlu menjalankan Python/OpenCV.
