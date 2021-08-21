<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );

$inputs = [
    [
        'mail'=>$inputs['mail'],
        'action'=>$inputs['mail']?'REGISTER_MAIL':'REGISTER_ANY',
        'token'=>$inputs['token'],
        'uses'=>$inputs['tokenUses'],
        'ttl'=>$inputs['tokenTTL']
    ]
];

$result = $UserHandler->createInviteTokens($inputs,['test'=>$test]);

?>