<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//Check auth
if( !( $auth->hasAction(GALLERY_GET_ALL_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot get image galleries image!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

//Address
if($inputs['address'] === null){
    if($test)
        echo 'Address must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!\IOFrame\Util\validator::validateRelativeFilePath($inputs['address'])){
    if($test)
        echo 'Address!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}