<?php 

require_once __DIR__.'/../_bootstrap.php';
require_once __DIR__.'/../../middleware/admin.php';
require_once __DIR__.'/../../app/controllers/AnnouncementController.php';

$c=new AnnouncementController($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    $a=postString('action');
    if($a==='create'){
        jsonResponse(['success'=>$c->create(postString('title'),postString('content'),postString('link')?:null,postString('audience'),(int)$_SESSION['user_id'])]);
    }

    if($a==='update'){
        jsonResponse(['success'=>$c->update((int)$_POST['id'],postString('title'),postString('content'),postString('link')?:null,postString('audience'))]);
    }

    if($a==='status'){
        jsonResponse(['success'=>$c->setStatus((int)$_POST['id'],postString('status'))]);
    }

    jsonResponse(['success'=>false],400);
}

jsonResponse(['success'=>true,'items'=>$c->all()]);
