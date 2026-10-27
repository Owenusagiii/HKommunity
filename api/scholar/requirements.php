<?php
require_once __DIR__.'/../_bootstrap.php';
require_once __DIR__.'/../../middleware/scholar.php';
require_once __DIR__.'/../../app/controllers/ScholarController.php';
require_once __DIR__.'/../../app/controllers/ScholarRequirementController.php';
require_once __DIR__.'/../../app/models/Notification.php';
require_once __DIR__.'/../../app/models/Coordinator.php';

$uid=(int)$_SESSION['user_id'];
$sc=(new ScholarController($pdo))->findByUserId($uid);
if(!$sc) jsonResponse(['success'=>false,'message'=>'Scholar profile not found.'],403);
$c=new ScholarRequirementController($pdo);

if($_SERVER['REQUEST_METHOD']==='POST' && postString('action')==='submit'){
    $ok=$c->submit((int)$_POST['id'],(int)$sc['id'],postString('gdrive_link'),$uid);
    if($ok){
        foreach((new Coordinator($pdo))->getAllCoordinators() as $co){
            if($co['year_level']===$sc['year_level'] && $co['status']==='active'){
                (new Notification($pdo))->createNotification((int)$co['user_id'],'Requirement Submitted',$sc['first_name'].' '.$sc['last_name'].' submitted a requirement.','requirement');
                break;
            }
        }
    }
    jsonResponse(['success'=>$ok]);
}
jsonResponse(['success'=>true,'items'=>$c->allForScholar((int)$sc['id'])]);
