<?php

if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );

switch($inputs["log"]) {
    case 'out':
        $UsersHandler->logOut(['test'=>$test]);
        $result = 0;
        break;
    default:
        $result =  $UsersHandler->logIn($inputs,['language'=>$inputs['language'],'test'=>$test]);
}