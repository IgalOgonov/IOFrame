<?php


if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );
//Hash the password
$result =  $UsersHandler->regUser($inputs,['test'=>$test,'language'=>$inputs['language'],'activateToken'=>$inputs['token']]);
