<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';

$id = $_SESSION['MAIL_CHANGE_ID'];
if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );

$result =  $UserHandler->changeMail($id,$inputs['newMail'],['test'=>$test,'keepActive'=>true]);

if($result === 0) {
    if (!$test) {
        unset($_SESSION['MAIL_CHANGE_ID']);
        unset($_SESSION['MAIL_CHANGE_EXPIRES']);
    }
    else
        echo 'Unsetting session variables!' . EOL;
}
