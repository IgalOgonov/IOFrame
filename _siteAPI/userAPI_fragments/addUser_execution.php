<?php

if(!defined('userHandler'))
    require __DIR__.'/../../_siteHandlers\userHandler.php';

try {

    if(!isset($userHandler))
        $userHandler = new IOFrame\userHandler(
            $settings,
            $defaultSettingsParams
        );
    //Hash the password
    $result =  $userHandler->regUser($inputs,['test'=>$test]);
}
catch(PDOException $e)
{
    if(!$test){
        $result =  $e->getMessage();
    }
    else $result =  "Error: " . $e->getMessage().EOL;
}
?>