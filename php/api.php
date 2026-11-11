<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
$action=$_GET['action']??'';
try {
 if($_SERVER['REQUEST_METHOD']==='POST') {
   $token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
   if(!hash_equals(csrf_token(),$token)) json_response(['ok'=>false,'message'=>'Token keselamatan tidak sah. Muat semula halaman.'],419);
   $data=json_decode(file_get_contents('php://input'),true)??[];
 } else $data=[];
 switch($action) {
  case 'login':
   $username=trim((string)($data['username']??'')); $password=(string)($data['password']??'');
   $q=db()->prepare('SELECT id,username,password_hash,role FROM users WHERE username=?');$q->execute([$username]);$u=$q->fetch();
   if(!$u||!password_verify($password,$u['password_hash'])) json_response(['ok'=>false,'message'=>'Nama pengguna atau kata laluan tidak tepat.'],401);
   session_regenerate_id(true);$_SESSION['user']=['id'=>(int)$u['id'],'username'=>$u['username'],'role'=>$u['role']];json_response(['ok'=>true]);
  case 'setup_admin':
   $name=trim((string)($data['username']??''));$pass=(string)($data['password']??'');
   if(strlen($name)<3||strlen($pass)<10) json_response(['ok'=>false,'message'=>'Nama pengguna minimum 3 aksara dan kata laluan minimum 10 aksara.'],422);
   if((int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()>0) json_response(['ok'=>false,'message'=>'Admin sudah diwujudkan.'],409);
   $q=db()->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,'admin')");$q->execute([$name,password_hash($pass,PASSWORD_DEFAULT)]);
   session_regenerate_id(true);$_SESSION['user']=['id'=>(int)db()->lastInsertId(),'username'=>$name,'role'=>'admin'];json_response(['ok'=>true]);
  case 'dashboard':
   $u=require_login();$people=(int)db()->query('SELECT COUNT(*) FROM people')->fetchColumn();
   $sql="SELECT COUNT(*) FROM attendance a JOIN people p ON p.id=a.person_id WHERE a.attendance_date=CURDATE()";
   if($u['role']!=='admin'){$q=db()->prepare($sql.' AND p.user_id=?');$q->execute([$u['id']]);$today=(int)$q->fetchColumn();}else $today=(int)db()->query($sql)->fetchColumn();
   json_response(['ok'=>true,'people'=>$u['role']==='admin'?$people:1,'today'=>$today]);
  case 'people':
   require_admin();$rows=db()->query('SELECT p.id,p.full_name,p.created_at,u.username FROM people p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.full_name')->fetchAll();json_response(['ok'=>true,'people'=>$rows]);
  case 'face_save':
   require_admin();$name=trim((string)($data['name']??''));$uid=($data['user_id']??'')!==''?(int)$data['user_id']:null;$samples=$data['descriptors']??[];
   if($name===''||count($samples)<2||count($samples)>10) json_response(['ok'=>false,'message'=>'Nama diperlukan dan sekurang-kurangnya 2 sampel wajah diperlukan.'],422);
   foreach($samples as $sample) if(!is_array($sample)||count($sample)!==128) json_response(['ok'=>false,'message'=>'Data descriptor wajah tidak sah.'],422);
   $descriptor=array_fill(0,128,0.0);foreach($samples as $sample)foreach($sample as $i=>$v){if(!is_numeric($v))json_response(['ok'=>false,'message'=>'Descriptor tidak sah.'],422);$descriptor[$i]+=(float)$v/count($samples);}
   if($uid){$q=db()->prepare("SELECT id FROM users WHERE id=? AND role='user'");$q->execute([$uid]);if(!$q->fetch())json_response(['ok'=>false,'message'=>'Akaun pengguna tidak sah.'],422);}
   $q=db()->prepare('INSERT INTO people(user_id,full_name,descriptor_json) VALUES(?,?,?)');$q->execute([$uid,$name,json_encode($descriptor)]);json_response(['ok'=>true,'message'=>'Wajah berjaya didaftarkan.']);
  case 'face_delete':
   require_admin();$q=db()->prepare('DELETE FROM people WHERE id=?');$q->execute([(int)($data['id']??0)]);json_response(['ok'=>$q->rowCount()>0,'message'=>'Profil wajah dipadam.']);
  case 'recognize':
   require_login();$d=$data['descriptor']??[];if(!is_array($d)||count($d)!==128)json_response(['ok'=>false,'message'=>'Data wajah tidak sah.'],422);
   $rows=db()->query('SELECT id,full_name,descriptor_json FROM people')->fetchAll();$best=null;$bestDistance=INF;
   foreach($rows as $p){$known=json_decode($p['descriptor_json'],true);if(!is_array($known)||count($known)!==128)continue;$sum=0;for($i=0;$i<128;$i++){$delta=(float)$d[$i]-(float)$known[$i];$sum+=$delta*$delta;}$dist=sqrt($sum);if($dist<$bestDistance){$bestDistance=$dist;$best=$p;}}
   $threshold=0.50;if(!$best||$bestDistance>$threshold)json_response(['ok'=>true,'recognized'=>false,'message'=>'Wajah tidak dikenali.']);
   $u=current_user();if($u['role']!=='admin'){$q=db()->prepare('SELECT user_id FROM people WHERE id=?');$q->execute([$best['id']]);$owner=$q->fetchColumn();if((int)$owner!==(int)$u['id'])json_response(['ok'=>true,'recognized'=>false,'message'=>'Wajah ini bukan profil akaun anda.']);}
   $q=db()->prepare('INSERT IGNORE INTO attendance(person_id,attendance_date,attendance_time,confidence) VALUES(?,CURDATE(),CURTIME(),?)');$q->execute([$best['id'],$bestDistance]);$marked=$q->rowCount()===1;
   json_response(['ok'=>true,'recognized'=>true,'name'=>$best['full_name'],'distance'=>round($bestDistance,3),'marked'=>$marked,'message'=>$marked?'Kehadiran berjaya direkod.':'Kehadiran hari ini telah direkodkan.']);
  case 'attendance':
   $u=require_login();$sql='SELECT a.id,p.full_name,a.attendance_date,a.attendance_time,a.confidence FROM attendance a JOIN people p ON p.id=a.person_id';$params=[];
   if($u['role']!=='admin'){$sql.=' WHERE p.user_id=?';$params[]=$u['id'];}$sql.=' ORDER BY a.attendance_date DESC,a.attendance_time DESC LIMIT 500';
   $q=db()->prepare($sql);$q->execute($params);json_response(['ok'=>true,'records'=>$q->fetchAll()]);
  case 'users':
   require_admin();$rows=db()->query("SELECT u.id,u.username,u.role,IF(p.id IS NULL,0,1) AS face_registered FROM users u LEFT JOIN people p ON p.user_id=u.id ORDER BY u.created_at DESC")->fetchAll();json_response(['ok'=>true,'users'=>$rows]);
  case 'user_create':
   require_admin();$name=trim((string)($data['username']??''));$pass=(string)($data['password']??'');if(strlen($name)<3||strlen($pass)<10)json_response(['ok'=>false,'message'=>'Nama pengguna minimum 3 aksara dan kata laluan minimum 10 aksara.'],422);
   $q=db()->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,'user')");try{$q->execute([$name,password_hash($pass,PASSWORD_DEFAULT)]);}catch(PDOException $e){json_response(['ok'=>false,'message'=>'Nama pengguna sudah digunakan.'],409);}json_response(['ok'=>true,'message'=>'Akaun pengguna berjaya ditambah.']);
  case 'user_delete':
   require_admin();$id=(int)($data['id']??0);if($id===(int)current_user()['id'])json_response(['ok'=>false,'message'=>'Tidak boleh memadam akaun sendiri.'],422);$q=db()->prepare("DELETE FROM users WHERE id=? AND role='user'");$q->execute([$id]);json_response(['ok'=>$q->rowCount()>0,'message'=>'Akaun dipadam.']);

  case 'user_update':
   require_admin();$id=(int)($data['id']??0);$name=trim((string)($data['username']??''));
   if($id<1||strlen($name)<3)json_response(['ok'=>false,'message'=>'Nama pengguna minimum 3 aksara.'],422);
   try{$q=db()->prepare("UPDATE users SET username=? WHERE id=? AND role='user'");$q->execute([$name,$id]);}catch(PDOException $e){json_response(['ok'=>false,'message'=>'Nama pengguna telah digunakan.'],409);}
   json_response(['ok'=>true,'message'=>'Nama pengguna dikemas kini.']);
  case 'attendance_delete':
   require_admin();$q=db()->prepare('DELETE FROM attendance WHERE id=?');$q->execute([(int)($data['id']??0)]);json_response(['ok'=>$q->rowCount()>0,'message'=>'Rekod kehadiran dipadam.']);
  case 'export_csv':
   $u=require_login();$sql='SELECT p.full_name,a.attendance_date,a.attendance_time,a.confidence FROM attendance a JOIN people p ON p.id=a.person_id';$params=[];
   if($u['role']!=='admin'){$sql.=' WHERE p.user_id=?';$params[]=$u['id'];}$sql.=' ORDER BY a.attendance_date DESC,a.attendance_time DESC';
   $q=db()->prepare($sql);$q->execute($params);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="smartattend-kehadiran.csv"');
   $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Nama','Tarikh','Masa','Skor padanan']);while($row=$q->fetch())fputcsv($out,$row);fclose($out);exit;
  default: json_response(['ok'=>false,'message'=>'Tindakan tidak dikenali.'],404);
 }
} catch(Throwable $e) { $code=http_response_code(); if($code<400)$code=500; json_response(['ok'=>false,'message'=>$code===500?'Ralat pelayan. Semak konfigurasi database.':$e->getMessage()],$code); }
