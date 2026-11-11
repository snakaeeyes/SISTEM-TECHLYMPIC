# SMARTATTEND AI — PHP Edition

Versi PHP untuk hosting Apache + PHP + MySQL. Kamera dan penjanaan face descriptor dijalankan dalam pelayar menggunakan face-api.js; PHP mengurus akaun, profil wajah dan rekod kehadiran.

## Keperluan
- PHP 8.0+ dengan PDO MySQL
- MySQL/MariaDB
- Pelayar moden dan kamera
- HTTPS untuk akses kamera pada hosting (localhost juga dibenarkan)

## Pemasangan
1. Muat naik kandungan repositori ke folder htdocs.
2. Cipta pangkalan data MySQL melalui panel hosting.
3. Import database/schema.sql melalui phpMyAdmin.
4. Edit config/db-config.php dan masukkan hostname, nama database, username dan password MySQL hosting.
5. Buka laman web melalui HTTPS dan pilih Pendaftaran admin pertama.
6. Log masuk dan tambah akaun melalui menu Pengguna.
7. Setiap pengguna log masuk, buka Daftar / Kenal Wajah, benarkan kamera dan simpan profil wajah.
8. Gunakan Kenal pasti & rekod kehadiran untuk merekod kehadiran.

## Keselamatan dan privasi
- Jangan simpan kata laluan database sebenar dalam repositori awam.
- Face descriptor ialah data biometrik. Dapatkan persetujuan pengguna dan hadkan akses database.
- Ambang pengecaman perlu diuji dalam pelbagai keadaan pencahayaan sebelum penggunaan rasmi.
- Model face-api.js dimuatkan daripada CDN; sambungan internet diperlukan pada peranti pengguna.
- Versi ini menggunakan pengecaman wajah dalam pelayar kerana hosting shared PHP lazimnya tidak menyokong servis Python/OpenCV yang berjalan berterusan.
