<?php


$id = $_SESSION['MAIL_CHANGE_ID'];
if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );

$result =  $UsersHandler->changeMail($id,$inputs['newMail'],['test'=>$test,'keepActive'=>true]);

if($result === 0) {
    if (!$test) {
        unset($_SESSION['MAIL_CHANGE_ID']);
        unset($_SESSION['MAIL_CHANGE_EXPIRES']);
    }
    else
        echo 'Unsetting session variables!' . EOL;
}
