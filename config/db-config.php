<?php
declare(strict_types=1);
// Isi maklumat MySQL daripada panel hosting. Jangan muat naik kata laluan sebenar ke repositori awam.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'nama_database');
define('DB_USER', getenv('DB_USER') ?: 'nama_pengguna');
define('DB_PASS', getenv('DB_PASS') ?: 'kata_laluan');
