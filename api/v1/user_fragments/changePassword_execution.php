<?php


$id = $_SESSION['PWD_RESET_ID'];
if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );

$result = $UsersHandler->changePassword($id,$inputs['newPassword'],['test'=>$test]);

if($result === 0){
    if(!$test){
        unset($_SESSION['PWD_RESET_ID']);
        unset($_SESSION['PWD_RESET_EXPIRES']);
    }
    else
        echo 'Unsetting session variables!'.EOL;
}
