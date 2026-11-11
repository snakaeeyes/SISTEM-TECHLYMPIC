<?php
require_once __DIR__.'/config/bootstrap.php';
$page = $_GET['page'] ?? (logged_in() ? 'dashboard' : 'login');
if ($page === 'logout') { session_destroy(); header('Location: index.php?page=login'); exit; }

if ($page === 'login' && $_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $stmt=$pdo->prepare('SELECT id,username,password_hash,role FROM users WHERE username=?');
    $stmt->execute([trim($_POST['username'] ?? '')]); $u=$stmt->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true); unset($u['password_hash']); $_SESSION['user']=$u;
        header('Location: index.php?page=dashboard'); exit;
    }
    flash('Nama pengguna atau kata laluan tidak tepat.','danger');
}
if ($page==='setup-admin' && $_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($count>0) { flash('Akaun admin sudah didaftarkan.','warning'); header('Location: index.php?page=login'); exit; }
    $name=trim($_POST['username'] ?? ''); $pass=$_POST['password'] ?? '';
    if ($name==='' || strlen($pass)<8) { flash('Isi nama pengguna dan kata laluan sekurang-kurangnya 8 aksara.','danger'); }
    else { $s=$pdo->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?, 'admin')"); $s->execute([$name,password_hash($pass,PASSWORD_DEFAULT)]); flash('Admin berjaya dicipta. Sila login.','success'); header('Location: index.php?page=login'); exit; }
}
if ($page==='register-user' && $_SERVER['REQUEST_METHOD']==='POST') {
    require_admin(); verify_csrf();
    $name=trim($_POST['username'] ?? ''); $pass=$_POST['password'] ?? ''; $role=($_POST['role'] ?? 'user')==='admin'?'admin':'user';
    if ($name==='' || strlen($pass)<8) flash('Nama pengguna diperlukan dan kata laluan minimum 8 aksara.','danger');
    else { try { $s=$pdo->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)'); $s->execute([$name,password_hash($pass,PASSWORD_DEFAULT),$role]); flash('Pengguna berjaya ditambah.','success'); } catch(PDOException $e) { flash('Nama pengguna telah digunakan.','danger'); } }
    header('Location: index.php?page=users'); exit;
}
if ($page==='delete-user' && $_SERVER['REQUEST_METHOD']==='POST') {
    require_admin(); verify_csrf(); $id=(int)($_POST['id']??0);
    if ($id===(int)$_SESSION['user']['id']) flash('Anda tidak boleh memadam akaun sendiri.','danger');
    else { $s=$pdo->prepare('DELETE FROM users WHERE id=?'); $s->execute([$id]); flash('Pengguna dipadam.','success'); }
    header('Location: index.php?page=users'); exit;
}
if ($page==='dashboard' || $page==='attendance' || $page==='register-face' || $page==='users') require_login();
if ($page==='users' || $page==='register-user') require_admin();

$allowed=['login','setup-admin','dashboard','attendance','register-face','users','register-user'];
if (!in_array($page,$allowed,true)) $page=logged_in()?'dashboard':'login';
$view=__DIR__.'/templates/'.$page.'.php';
if (!is_file($view)) $view=__DIR__.'/templates/login.php';
include __DIR__.'/templates/base.php';
