<?php

require_once $settings->getSetting('absPathToRoot').'/_util/validator.php';

if(!isset($inputs['newPassword']) || !IOFrame\validator::validatePassword($inputs['newPassword'])){
    if($test)
        echo 'Invalid password!';
    exit('-1');
}

if(!isset($_SESSION['PWD_RESET_ID']) || !isset($_SESSION['PWD_RESET_EXPIRES']) ){
    if($test)
        echo 'Password reset not authorized!';
    exit('-2');
}

if($_SESSION['PWD_RESET_EXPIRES']<time()){
    if($test)
        echo 'Password reset token expired!';
    exit('2');
}
