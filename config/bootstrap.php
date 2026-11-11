<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
    session_start();
}
require_once __DIR__ . '/db-config.php';
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Sambungan pangkalan data gagal. Semak config/db-config.php.');
}
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function logged_in(): bool { return isset($_SESSION['user']); }
function require_login(): void { if (!logged_in()) { header('Location: index.php?page=login'); exit; } }
function require_admin(): void { require_login(); if (($_SESSION['user']['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses ditolak.'); } }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) { http_response_code(419); exit('Permintaan tidak sah. Muat semula halaman.'); } }
function flash(string $message, string $type='info'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
