<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../IOFrame/Handlers/UserHandler.php';

try {

    if(!isset($UserHandler))
        $UserHandler = new IOFrame\Handlers\UserHandler(
            $settings,
            $defaultSettingsParams
        );
    //Hash the password
    $result =  $UserHandler->regUser($inputs,['test'=>$test]);
}
catch(PDOException $e)
{
    if(!$test){
        $result =  $e->getMessage();
    }
    else $result =  "Error: " . $e->getMessage().EOL;
}
?>