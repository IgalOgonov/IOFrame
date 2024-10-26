<?php

if($action === 'updateUser'){
    $actionInputs = [
        'username' =>$inputs['username'],
        'email' => $inputs['email'],
        'phone' => $inputs['phone'],
        'active' => $inputs['active'],
        'created' => $inputs['created'],
        'bannedDate' =>$inputs['bannedDate'],
        'lockedDate' =>$inputs['lockedDate'],
        'suspiciousDate' =>$inputs['suspiciousDate']
    ];
    if($inputs['reset2FA']){
        $actionInputs['2FASecret'] = false;
        $actionInputs['require2FA'] = false;
    }
    elseif($inputs['require2FA']!==null){
        $actionInputs['require2FA'] = (bool)$inputs['require2FA'];
    }
    if(!isset($inputs['logUserOut']))
        $inputs['logUserOut'] = $inputs['email'] || $inputs['phone'] || $inputs['active'] || $inputs['bannedDate'] || $inputs['suspiciousDate'] || $inputs['lockedDate'];
}
elseif($action === 'require2FA')
    $actionInputs = [
        'require2FA' =>$inputs['require2FA']
    ];
elseif($action === 'confirmPhone')
    $actionInputs = [
        'phone' => $inputs['phone'],
        'require2FA' =>$inputs['require2FA']
    ];
elseif($action === 'confirmApp')
    $actionInputs = [
        '2FASecret' => $expectedSecret,
        'require2FA' =>$inputs['require2FA']
    ];
$result = $UsersHandler->updateUser(
    $inputs['id'],
    $actionInputs,
    'ID',
    ['test'=>$test]
);
if($inputs['logUserOut']??false){
    $sessionId = $SQLManager->selectFromTable($SQLManager->getSQLPrefix().'USERS',['ID',$inputs['id'],'='],['SessionID']);
    if(count($sessionId)>0)
        $sessionId = $sessionId[0]['SessionID'];
    if($sessionId)
        $UsersHandler->logOut([
            'test'=>$test,
            'oldSesID'=>session_id()
        ]);
    }