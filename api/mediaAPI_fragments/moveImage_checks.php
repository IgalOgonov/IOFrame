<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//Check auth
if( !( $auth->hasAction(IMAGE_MOVE_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot move image!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

//Addresses
if($inputs['oldAddress'] === null || $inputs['newAddress'] === null){
    if($test)
        echo 'Both old and new address must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!\IOFrame\Util\validator::validateRelativeFilePath($inputs['oldAddress'])){
    if($test)
        echo 'Invalid old address!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!\IOFrame\Util\validator::validateRelativeFilePath($inputs['newAddress'])){
    if($test)
        echo 'Invalid new address!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}