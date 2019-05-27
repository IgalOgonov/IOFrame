<?php

if(!defined('userHandler'))
    require __DIR__.'/../../_siteHandlers/userHandler.php';

if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

$result = $userHandler->banUser($inputs['minutes'],$inputs['id'],['test'=>$test]);







