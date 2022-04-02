<?php

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );

switch($inputs["log"]) {
    case 'out':
        $UserHandler->logOut(['test'=>$test]);
        $result = 0;
        break;
    default:
        $result =  $UserHandler->logIn($inputs,['language'=>$inputs['language'],'test'=>$test]);
}