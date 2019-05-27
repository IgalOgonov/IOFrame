<?php

if(!defined('userHandler'))
    require __DIR__.'/../../_siteHandlers/userHandler.php';

$id = $_SESSION['PWD_RESET_ID'];
if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

$result = $userHandler->changePassword($id,$inputs['newPassword'],['test'=>$test]);

if(!$test){
    unset($_SESSION['PWD_RESET_ID']);
    unset($_SESSION['PWD_RESET_EXPIRES']);
}
else
    echo 'Unsetting session variables!'.EOL;

