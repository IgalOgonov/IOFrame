<?php

require_once __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';
$UserHandler = new \IOFrame\Handlers\UserHandler($settings,['SQLHandler'=>$SQLHandler]);

if($action === 'updateUser'){
    $actionInputs = [
        'username' =>$inputs['username'],
        'email' => $inputs['email'],
        'phone' => $inputs['phone'],
        'active' => $inputs['active'],
        'created' => $inputs['created'],
        'bannedDate' =>$inputs['bannedDate'],
        'suspiciousDate' =>$inputs['suspiciousDate']
    ];
    if($inputs['reset2FA']){
        $actionInputs['2FASecret'] = false;
        $actionInputs['require2FA'] = false;
    }
    elseif($inputs['require2FA']!==null){
        $actionInputs['require2FA'] = (bool)$inputs['require2FA'];
    }
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
$result = $UserHandler->updateUser(
    $inputs['id'],
    $actionInputs,
    'ID',
    ['test'=>$test]
);
