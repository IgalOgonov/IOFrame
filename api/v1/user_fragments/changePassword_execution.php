<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';

$id = $_SESSION['PWD_RESET_ID'];
if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );

$result = $UserHandler->changePassword($id,$inputs['newPassword'],['test'=>$test]);

if($result === 0){
    if(!$test){
        unset($_SESSION['PWD_RESET_ID']);
        unset($_SESSION['PWD_RESET_EXPIRES']);
    }
    else
        echo 'Unsetting session variables!'.EOL;
}
