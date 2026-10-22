<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$user = $_SESSION['u'] ?? null;
function out($data, $code=200) { http_response_code($code); echo json_encode($data); exit; }
function q($sql, $params=[]) { global $db; $stmt=$db->prepare($sql); $stmt->execute($params); return $stmt; }
function need(...$roles) { global $user; if (!$user) out(['error'=>'Login required. Please sign in again.'],401); if ($roles && !in_array($user['role'],$roles,true)) out(['error'=>'Forbidden'],403); }
function audit($action,$details='') { global $user; q('INSERT INTO audit_log(user_id,action,details) VALUES(?,?,?)',[$user['id']??null,$action,substr((string)$details,0,255)]); }
function gp($p) { return $p>=90?10:($p>=80?9:($p>=70?8:($p>=60?7:($p>=50?6:($p>=40?5:0))))); }
if ($_SERVER['REQUEST_METHOD']==='POST' && $action!=='login' && $action!=='logout' && !hash_equals($_SESSION['csrf']??'', $_SERVER['HTTP_X_CSRF']??'')) out(['error'=>'Session security token expired. Refresh and sign in again.'],403);
$tables=['departments'=>['name','code'],'courses'=>['dept_id','code','name','credits','semester'],'students'=>['roll_no','name','email','phone','dob','dept_id','year','semester','address','status']];
try {
 switch ($action) {
 case 'login':
   if (($_SESSION['fail']??0)>=5 && time()-($_SESSION['ft']??0)<60) out(['error'=>'Too many attempts. Wait 60 seconds and try again.'],429);
   $username=trim((string)($input['username']??'')); $password=(string)($input['password']??'');
   $record=q('SELECT * FROM users WHERE username=?',[$username])->fetch();
   if (!$record && $username==='admin') {
     $count=(int)q('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
     if ($count===0) {
       q("INSERT INTO users(username,password_hash,role) VALUES(?,?, 'admin')",['admin',password_hash('admin123',PASSWORD_DEFAULT)]);
       $record=q('SELECT * FROM users WHERE username=?',['admin'])->fetch();
     }
   }
   if (!$record || !password_verify($password,$record['password_hash'])) { $_SESSION['fail']=($_SESSION['fail']??0)+1; $_SESSION['ft']=time(); out(['error'=>'Invalid username or password. Check the details and try again.'],401); }
   session_regenerate_id(true); $_SESSION['fail']=0; $_SESSION['csrf']=bin2hex(random_bytes(24));
   $_SESSION['u']=['id'=>$record['id'],'username'=>$record['username'],'role'=>$record['role'],'student_id'=>$record['student_id']??null];
   $user=$_SESSION['u']; audit('login');
   out(['user'=>$user,'csrf'=>$_SESSION['csrf']]);
 case 'me': need(); out(['user'=>$user,'csrf'=>$_SESSION['csrf']??'']);
 case 'logout': $_SESSION=[]; if (ini_get('session.use_cookies')) { $params=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']); } session_destroy(); out(['ok'=>1]);
 case 'password':
   need(); $record=q('SELECT * FROM users WHERE id=?',[$user['id']])->fetch();
   if (!password_verify($input['old']??'',$record['password_hash']) || strlen($input['new']??'')<6) out(['error'=>'Old password is incorrect or new password is shorter than 6 characters.'],400);
   q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($input['new'],PASSWORD_DEFAULT),$user['id']]); out(['ok'=>1]);
 case 'list':
   need('admin','faculty'); $entity=$_GET['entity']??''; if (!isset($tables[$entity])) out(['error'=>'Invalid entity'],400); $search='%'.($_GET['q']??'').'%';
   if ($entity==='students') $rows=q('SELECT s.*,d.code AS dept FROM students s LEFT JOIN departments d ON d.id=s.dept_id WHERE s.name LIKE ? OR s.roll_no LIKE ? ORDER BY s.roll_no',[$search,$search]);
   elseif ($entity==='courses') $rows=q('SELECT c.*,d.code AS dept FROM courses c JOIN departments d ON d.id=c.dept_id WHERE c.name LIKE ? OR c.code LIKE ? ORDER BY c.code',[$search,$search]);
   else $rows=q('SELECT * FROM departments WHERE name LIKE ? OR code LIKE ? ORDER BY code',[$search,$search]);
   out($rows->fetchAll());
 case 'save':
   need('admin'); $entity=$input['entity']??''; if (!isset($tables[$entity])) out(['error'=>'Invalid entity'],400); $data=$input['data']??[]; $values=[];
   foreach ($tables[$entity] as $column) $values[$column]=(($data[$column]??'')==='')?null:$data[$column];
   if (!empty($data['id'])) { q("UPDATE $entity SET ".implode(',',array_map(fn($column)=>"$column=?",$tables[$entity]))." WHERE id=?",[...array_values($values),$data['id']]); audit("update $entity",$data['id']); }
   else { q("INSERT INTO $entity(".implode(',',$tables[$entity]).") VALUES(".implode(',',array_fill(0,count($tables[$entity]),'?')).")",array_values($values)); $id=$db->lastInsertId(); audit("create $entity",$id);
     if ($entity==='students') q("INSERT INTO users(username,password_hash,role,student_id) VALUES(?,?, 'student',?)",[$values['roll_no'],password_hash($values['roll_no'],PASSWORD_DEFAULT),$id]); }
   out(['ok'=>1]);
 case 'delete':
   need('admin'); $entity=$input['entity']??''; if (!isset($tables[$entity])) out(['error'=>'Invalid entity'],400); q("DELETE FROM $entity WHERE id=?",[$input['id']??0]); audit("delete $entity",$input['id']??''); out(['ok'=>1]);
 case 'options':
   need(); out(['departments'=>q('SELECT id,name,code FROM departments ORDER BY code')->fetchAll(),'courses'=>q('SELECT id,code,name FROM courses ORDER BY code')->fetchAll(),'students'=>$user['role']==='student'?[]:q('SELECT id,roll_no,name FROM students ORDER BY roll_no')->fetchAll()]);
 case 'attendance_get':
   need('admin','faculty'); out(q("SELECT s.id,s.roll_no,s.name,a.status FROM courses c JOIN students s ON s.dept_id=c.dept_id AND s.semester=c.semester AND s.status='Active' LEFT JOIN attendance a ON a.student_id=s.id AND a.course_id=c.id AND a.date=? WHERE c.id=? ORDER BY s.roll_no",[$_GET['date']??date('Y-m-d'),$_GET['course_id']??0])->fetchAll());
 case 'attendance_save':
   need('admin','faculty'); $db->beginTransaction();
   foreach (($input['records']??[]) as $row) q('INSERT INTO attendance(student_id,course_id,date,status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)',[$row['student_id'],$input['course_id'],$input['date'],$row['status']]);
   $db->commit(); audit('attendance',($input['course_id']??'').' '.($input['date']??'')); out(['ok'=>1]);
 case 'attendance_summary':
   need(); $studentId=$user['role']==='student'?$user['student_id']:(int)($_GET['student_id']??0);
   out(q("SELECT c.code,c.name,SUM(a.status IN('P','L')) AS present,COUNT(*) AS total,ROUND(100*SUM(a.status IN('P','L'))/COUNT(*),1) AS pct FROM attendance a JOIN courses c ON c.id=a.course_id WHERE a.student_id=? GROUP BY c.id",[$studentId])->fetchAll());
 case 'marks_get':
   need('admin','faculty'); out(q("SELECT s.id,s.roll_no,s.name,m.score,m.max_score FROM courses c JOIN students s ON s.dept_id=c.dept_id AND s.semester=c.semester LEFT JOIN marks m ON m.student_id=s.id AND m.course_id=c.id AND m.exam=? WHERE c.id=? ORDER BY s.roll_no",[$_GET['exam']??'Internal',$_GET['course_id']??0])->fetchAll());
 case 'marks_save':
   need('admin','faculty'); $db->beginTransaction(); $maximum=(float)($input['max']??100);
   foreach (($input['records']??[]) as $row) { if ($row['score']===''||$row['score']===null) continue; $score=(float)$row['score']; if ($score<0||$score>$maximum) { $db->rollBack(); out(['error'=>'Score is outside the allowed range for student '.$row['student_id']],400); } q('INSERT INTO marks(student_id,course_id,exam,score,max_score) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),max_score=VALUES(max_score)',[$row['student_id'],$input['course_id'],$input['exam'],$score,$maximum]); }
   $db->commit(); audit('marks',($input['course_id']??'').' '.($input['exam']??'')); out(['ok'=>1]);
 case 'transcript':
   need(); $studentId=$user['role']==='student'?$user['student_id']:(int)($_GET['student_id']??0);
   $student=q('SELECT s.*,d.name AS dept FROM students s LEFT JOIN departments d ON d.id=s.dept_id WHERE s.id=?',[$studentId])->fetch();
   if (!$student) out(['student'=>null,'courses'=>[],'sgpa'=>[],'cgpa'=>0]);
   $rows=q('SELECT c.code,c.name,c.credits,c.semester,SUM(m.score) AS sc,SUM(m.max_score) AS mx FROM marks m JOIN courses c ON c.id=m.course_id WHERE m.student_id=? GROUP BY c.id ORDER BY c.semester,c.code',[$studentId])->fetchAll();
   $sem=[]; $totalPoints=0; $totalCredits=0;
   foreach ($rows as &$row) { $row['pct']=$row['mx']?(round($row['sc']/$row['mx']*100,1)):0; $row['gp']=gp($row['pct']); $semester=$row['semester']; $sem[$semester]['p']=($sem[$semester]['p']??0)+$row['gp']*$row['credits']; $sem[$semester]['c']=($sem[$semester]['c']??0)+$row['credits']; $totalPoints+=$row['gp']*$row['credits']; $totalCredits+=$row['credits']; }
   unset($row); $sgpa=[]; foreach ($sem as $semester=>$value) $sgpa[$semester]=$value['c']?round($value['p']/$value['c'],2):0;
   out(['student'=>$student,'courses'=>$rows,'sgpa'=>$sgpa,'cgpa'=>$totalCredits?round($totalPoints/$totalCredits,2):0]);
 case 'stats':
   need('admin','faculty');
   out(['students'=>q("SELECT COUNT(*) AS c FROM students WHERE status='Active'")->fetch()['c'],'courses'=>q('SELECT COUNT(*) AS c FROM courses')->fetch()['c'],'departments'=>q('SELECT COUNT(*) AS c FROM departments')->fetch()['c'],'avg_att'=>round((float)(q("SELECT AVG(status IN('P','L'))*100 AS v FROM attendance")->fetch()['v']??0),1),'by_dept'=>q('SELECT d.code AS label,COUNT(s.id) AS value FROM departments d LEFT JOIN students s ON s.dept_id=d.id GROUP BY d.id')->fetchAll(),'low_att'=>q("SELECT s.roll_no,s.name,ROUND(100*SUM(a.status IN('P','L'))/COUNT(*),1) AS pct FROM attendance a JOIN students s ON s.id=a.student_id GROUP BY s.id HAVING pct<75 ORDER BY pct LIMIT 10")->fetchAll(),'toppers'=>q("SELECT s.roll_no,s.name,ROUND(AVG(100*m.score/m.max_score),1) AS avg FROM marks m JOIN students s ON s.id=m.student_id GROUP BY s.id ORDER BY avg DESC LIMIT 5")->fetchAll()]);
 case 'audit':
   need('admin'); out(q('SELECT l.*,u.username FROM audit_log l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 100')->fetchAll());
 case 'export':
   need('admin','faculty'); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=students.csv'); $file=fopen('php://output','w'); fputcsv($file,['Roll','Name','Email','Phone','Department','Year','Semester','Status']);
   foreach(q('SELECT s.roll_no,s.name,s.email,s.phone,d.code,s.year,s.semester,s.status FROM students s LEFT JOIN departments d ON d.id=s.dept_id')->fetchAll() as $row) fputcsv($file,$row); exit;
 default: out(['error'=>'Unknown action'],404);
 }
} catch (Throwable $e) {
 if (isset($db) && $db->inTransaction()) $db->rollBack();
 out(['error'=>'Database operation failed. Check that sql/schema.sql has been imported and required fields are filled.'],400);
}
