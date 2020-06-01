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


$valid = \IOFrame\Util\validator::validateRelativeFilePath($inputs['address']);
$valid = $valid || filter_var($inputs['address'],FILTER_VALIDATE_URL);

if(!$valid){
    if($test)
        echo 'Address invalid!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}