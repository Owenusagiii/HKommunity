<?php require_once __DIR__.'/../_bootstrap.php';
require_once __DIR__.'/../../middleware/scholar.php';
require_once __DIR__.'/../../app/controllers/ScholarController.php';
require_once __DIR__.'/../../app/controllers/ReportController.php';
require_once __DIR__.'/../../app/models/Coordinator.php';
require_once __DIR__.'/../../app/models/Notification.php';
$uid=(int)$_SESSION['user_id'];
$sc=(new ScholarController($pdo))->findByUserId($uid);
if(!$sc)jsonResponse(['success'=>false],403);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $cs=(new Coordinator($pdo))->getAllCoordinators();
    $target=null;
    foreach($cs as $c){
        if($c['year_level']===$sc['year_level']&&$c['status']==='active'){
            $target=(int)$c['user_id'];
            break;
        }
    }
    if(!$target)jsonResponse(['success'=>false,'message'=>'No active coordinator is assigned to this year level.'],400);
    $ok=(new ReportController($pdo))->create($uid,$target,postString('subject'),postString('message'));
    if($ok)(new Notification($pdo))->createNotification($target,'New Scholar Report',$sc['first_name'].' '.$sc['last_name'].' submitted a report.','report');
    jsonResponse(['success'=>$ok]);
}
jsonResponse(['success'=>true,'items'=>(new ReportController($pdo))->forUser($uid)]);
