<?php
declare(strict_types=1);
require_once __DIR__ . '/php/bootstrap.php';
$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard','camera','people','attendance','users','login','setup'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';
$user = current_user();
if (!$user && !in_array($page, ['login','setup'], true)) { header('Location: ?page=login'); exit; }
if ($user && in_array($page, ['login','setup'], true)) { header('Location: ?page=dashboard'); exit; }
$isAdmin = $user && $user['role'] === 'admin';
if (!$isAdmin && in_array($page, ['people','users'], true)) { http_response_code(403); $page='dashboard'; }
$csrf = csrf_token();
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= e($csrf) ?>"><title>SMARTATTEND AI</title>
<link rel="stylesheet" href="assets/css/style.css">
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/build/face-api.min.js"></script>
<script defer src="assets/js/app.js"></script></head><body>
<?php if ($user): ?><aside class="sidebar"><a class="brand" href="?page=dashboard"><span class="brand-icon">◎</span><span>SMARTATTEND <b>AI</b><small>NEUROFACE TECHNOLOGY</small></span></a><nav>
<a href="?page=dashboard">▦ Dashboard</a><a href="?page=camera">◉ Kamera AI</a><a href="?page=attendance">▤ Rekod Kehadiran</a>
<?php if($isAdmin): ?><a href="?page=people">♙ Pendaftaran Wajah</a><a href="?page=users">♧ Pengurusan Pengguna</a><?php endif; ?>
</nav><div class="side-bottom"><span><?=e($user['username'])?> · <?=e(strtoupper($user['role']))?></span><a href="logout.php">Log keluar ↗</a></div></aside><?php endif; ?>
<main class="<?= $user ? 'main' : 'auth-main' ?>">
<?php if($page==='login'): ?>
<section class="auth-card"><div class="eyebrow">NEUROFACE TECHNOLOGY</div><h1>SMARTATTEND <em>AI</em></h1><p>Sistem kehadiran pintar menggunakan pengecaman wajah.</p><form id="loginForm"><label>Nama pengguna<input name="username" required autocomplete="username"></label><label>Kata laluan<input name="password" type="password" required autocomplete="current-password"></label><button class="btn primary">Log masuk</button><div id="formMessage" class="message"></div></form><a class="muted-link" href="?page=setup">Persediaan akaun pentadbir pertama</a></section>
<?php elseif($page==='setup'): ?>
<section class="auth-card"><div class="eyebrow">SETUP PERTAMA</div><h1>Cipta <em>Admin</em></h1><p>Halaman ini hanya tersedia jika belum ada akaun admin.</p><form id="setupForm"><label>Nama pengguna<input name="username" required minlength="3"></label><label>Kata laluan<input name="password" type="password" required minlength="10"></label><button class="btn primary">Cipta akaun admin</button><div id="formMessage" class="message"></div></form></section>
<?php else: ?>
<header class="topbar"><div><div class="eyebrow">SMARTATTEND AI / <?=e(strtoupper($page))?></div><h2><?= ['dashboard'=>'Dashboard','camera'=>'Kamera Pengecaman AI','people'=>'Pendaftaran Wajah','attendance'=>'Rekod Kehadiran','users'=>'Pengurusan Pengguna'][$page] ?></h2></div><div class="status-pill"><span></span> Sistem PHP + MySQL</div></header>
<?php if($page==='dashboard'): ?>
<section class="hero"><div><div class="eyebrow">AI-POWERED ATTENDANCE</div><h1>Kehadiran pintar,<br><em>lebih pantas.</em></h1><p>Kenal pasti wajah melalui kamera peranti dan rekod kehadiran secara automatik. Daftar wajah sekali dan urus rekod dalam satu sistem.</p><a class="btn primary" href="?page=camera">Buka kamera AI →</a></div><div class="hero-orb">◎<small>FACE<br>RECOGNITION</small></div></section>
<div class="stats"><article class="stat"><span>Pengguna berdaftar</span><strong id="statPeople">—</strong></article><article class="stat"><span>Kehadiran hari ini</span><strong id="statToday">—</strong></article><article class="stat"><span>Status AI</span><strong class="cyan">Sedia</strong></article></div>
<section class="panel"><div class="panel-head"><h3>Aktiviti terkini</h3><a href="?page=attendance">Lihat semua →</a></div><div id="recentAttendance" class="table-wrap">Memuatkan rekod…</div></section>
<?php elseif($page==='camera'): ?>
<section class="panel camera-panel"><div class="panel-head"><div><h3>Live Face Recognition</h3><p class="muted">Benarkan akses kamera. Gunakan HTTPS atau localhost.</p></div><span id="aiStatus" class="tag">Memuatkan model AI…</span></div><div class="video-wrap"><video id="cameraVideo" autoplay muted playsinline></video><canvas id="cameraCanvas"></canvas><div id="cameraPlaceholder">◉<span>Kamera belum dimulakan</span></div></div><div class="camera-actions"><button id="startCamera" class="btn primary">Mulakan kamera</button><button id="stopCamera" class="btn secondary" disabled>Hentikan</button></div><p id="cameraMessage" class="message"></p></section>
<?php elseif($page==='people'): ?>
<section class="grid-two"><div class="panel"><h3>Daftar wajah baharu</h3><p class="muted">Masukkan nama, pilih akaun pengguna (pilihan), kemudian tangkap beberapa sampel wajah.</p><form id="faceForm"><label>Nama penuh<input name="name" required maxlength="100"></label><label>Pautkan akaun pengguna<select name="user_id" id="personUser"><option value="">Tidak dipautkan</option></select></label><button class="btn primary" type="button" id="captureFace">Tangkap & daftar wajah</button><div id="faceMessage" class="message"></div></form><div class="hint">Pastikan wajah jelas, pencahayaan mencukupi dan hanya seorang berada di hadapan kamera.</div></div><div class="panel"><h3>Pratonton kamera</h3><div class="video-wrap small"><video id="registerVideo" autoplay muted playsinline></video><canvas id="registerCanvas"></canvas><div id="registerPlaceholder">Kamera pendaftaran</div></div><button class="btn secondary" id="registerCamera">Aktifkan kamera</button></div></section><section class="panel"><div class="panel-head"><h3>Wajah berdaftar</h3><button class="btn secondary" id="reloadPeople">Muat semula</button></div><div id="peopleList" class="table-wrap">Memuatkan…</div></section>
<?php elseif($page==='attendance'): ?>
<section class="panel"><div class="panel-head"><div><h3>Rekod kehadiran</h3><p class="muted">Rekod harian dengan pencegahan kehadiran berganda.</p></div><a class="btn secondary" href="api.php?action=export_csv">Eksport CSV</a></div><div id="attendanceList" class="table-wrap">Memuatkan rekod…</div></section>
<?php elseif($page==='users'): ?>
<section class="grid-two"><div class="panel"><h3>Tambah akaun pengguna</h3><form id="userForm"><label>Nama pengguna<input name="username" required minlength="3"></label><label>Kata laluan<input name="password" type="password" required minlength="10"></label><button class="btn primary">Tambah pengguna</button><div id="userMessage" class="message"></div></form></div><div class="panel"><h3>Pengurusan akaun</h3><p class="muted">Akaun pengguna boleh dipautkan kepada profil wajah.</p><div id="usersList" class="table-wrap">Memuatkan…</div></div></section>
<?php endif; ?>
<?php endif; ?></main></body></html>