<?php


if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
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

$result = $UsersHandler->createInviteTokens($inputs,['test'=>$test]);
