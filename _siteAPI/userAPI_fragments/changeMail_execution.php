<?php

require_once $settings->getSetting('absPathToRoot').'/_siteHandlers/userHandler.php';

$id = $_SESSION['MAIL_CHANGE_ID'];
if(!isset($userHandler))
    $userHandler = new IOFrame\userHandler(
        $settings,
        $defaultSettingsParams
    );

$result =  $userHandler->changeMail($id,$_REQUEST['newMail'],$test);

if(!$test){
    unset($_SESSION['MAIL_CHANGE_ID']);
    unset($_SESSION['MAIL_CHANGE_EXPIRES']);
}
else
    echo 'Unsetting session variables!'.EOL;

