<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

if(!isset($inputs['newPassword']) || !IOFrame\Util\validator::validatePassword($inputs['newPassword'])){
    if($test)
        echo 'Invalid password!';
    exit(INPUT_VALIDATION_FAILURE);
}

if($_SESSION['PWD_RESET_EXPIRES']<time()){
    if($test)
        echo 'Password reset token expired!';
    exit('2');
}
