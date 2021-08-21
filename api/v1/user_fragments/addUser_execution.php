<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );
//Hash the password
$result =  $UserHandler->regUser($inputs,['test'=>$test,'activateToken'=>$inputs['token']]);

?>