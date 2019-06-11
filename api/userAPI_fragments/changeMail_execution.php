<?php

if(!defined('userHandler'))
    require __DIR__ . '/../../handlers/userHandler.php';

$id = $_SESSION['MAIL_CHANGE_ID'];
if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

$result =  $userHandler->changeMail($id,$inputs['newMail'],['test'=>$test]);

if(!$test){
    unset($_SESSION['MAIL_CHANGE_ID']);
    unset($_SESSION['MAIL_CHANGE_EXPIRES']);
}
else
    echo 'Unsetting session variables!'.EOL;

