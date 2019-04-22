<?php

if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

switch($inputs["log"]) {
    case 'out':
        $userHandler->logOut([],$test);
        $result = 0;
        break;
    default:
        $result =  $userHandler->logIn($inputs,$test);
}