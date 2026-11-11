<?php
require_once __DIR__.'/../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (!logged_in()) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Sila log masuk.']); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
verify_csrf();
$input=json_decode(file_get_contents('php://input'),true) ?: [];
$action=$input['action'] ?? '';
$descriptor=$input['descriptor'] ?? null;
if (!is_array($descriptor) || count($descriptor)<64 || count($descriptor)>1024) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Data wajah tidak sah.']); exit; }
foreach($descriptor as $v) if(!is_numeric($v) || !is_finite((float)$v)){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Data wajah tidak sah.']);exit;}
if($action==='save'){
 $json=json_encode(array_map('floatval',$descriptor),JSON_THROW_ON_ERROR);
 $s=$pdo->prepare('INSERT INTO face_profiles(user_id,descriptor) VALUES(?,?) ON DUPLICATE KEY UPDATE descriptor=VALUES(descriptor)');
 $s->execute([$_SESSION['user']['id'],$json]);
 echo json_encode(['ok'=>true,'message'=>'Profil wajah berjaya disimpan untuk akaun anda.']); exit;
}
if($action==='recognize'){
 $profiles=$pdo->query('SELECT f.user_id,f.descriptor,u.username FROM face_profiles f JOIN users u ON u.id=f.user_id')->fetchAll();
 $best=null;$bestDistance=INF;
 foreach($profiles as $p){
  $known=json_decode($p['descriptor'],true); if(!is_array($known)||count($known)!==count($descriptor))continue;
  $sum=0; foreach($descriptor as $i=>$v){$d=(float)$v-(float)$known[$i];$sum+=$d*$d;}
  $distance=sqrt($sum);
  if($distance<$bestDistance){$bestDistance=$distance;$best=$p;}
 }
 // Ambang konservatif; pengesahan identiti tetap perlu diuji dalam keadaan sebenar.
 if(!$best || $bestDistance>0.50){echo json_encode(['ok'=>true,'matched'=>false,'message'=>'Wajah tidak dikenali.']);exit;}
 $s=$pdo->prepare('INSERT IGNORE INTO attendance(user_id,attendance_date,attendance_time) VALUES(?,CURDATE(),CURTIME())');
 $s->execute([$best['user_id']]);
 echo json_encode(['ok'=>true,'matched'=>true,'marked'=>$s->rowCount()===1,'name'=>$best['username'],'distance'=>round($bestDistance,3),'message'=>$s->rowCount()===1?'Kehadiran berjaya direkodkan.':'Kehadiran hari ini telah direkodkan.']); exit;
}
http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Tindakan tidak diketahui.']);
