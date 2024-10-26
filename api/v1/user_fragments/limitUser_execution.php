<?php


if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );

if($action === 'banUser')
    $result = $UsersHandler->banUser($inputs['minutes'],$inputs['id'],['test'=>$test]);
elseif($action === 'suspectUser')
    $result = $UsersHandler->suspectUser($inputs['minutes'],$inputs['id'],['test'=>$test]);
elseif($action === 'lockUser')
    $result = $UsersHandler->lockUser($inputs['minutes'],$inputs['id'],['test'=>$test]);

//User needs to be logged out for this to take effect
if( ($result === 0) && ($action !== 'suspectUser') ){
    $sessionId = $SQLManager->selectFromTable($SQLManager->getSQLPrefix().'USERS',['ID',$inputs['id'],'='],['SessionID'],['test'=>$test]);
    if(count($sessionId)>0)
        $sessionId = $sessionId[0]['SessionID'];
    if($sessionId)
        $UsersHandler->logOut([
            'test'=>$test,
            'oldSesID'=>session_id()
        ]);
}






