<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  $host = getenv('DB_HOST') ?: 'localhost';
  $name = getenv('DB_NAME') ?: 'smartattend_ai';
  $user = getenv('DB_USER') ?: 'root';
  $pass = getenv('DB_PASS') ?: '';
  $port = getenv('DB_PORT') ?: '3306';
  $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
  return $pdo;
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): array {
  $u=current_user(); if(!$u){ http_response_code(401); throw new RuntimeException('Sila log masuk dahulu.'); } return $u;
}
function require_admin(): array {
  $u=require_login(); if($u['role']!=='admin'){http_response_code(403);throw new RuntimeException('Akses admin diperlukan.');} return $u;
}
function csrf_token(): string {
  if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function json_response(array $data,int $status=200): never {
  http_response_code($status); header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
