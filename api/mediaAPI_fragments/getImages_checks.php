<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

if( !( $auth->hasAction(IMAGE_GET_ALL_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot get all images'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

if($inputs['address'] !== null){

    if(!\IOFrame\Util\validator::validateRelativeDirectoryPath($inputs['address'])){
        if($test)
            echo 'Invalid address!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    //Trim the address
    if($inputs['address'][strlen($inputs['address'])-1] === '/')
        $inputs['address'] = substr($inputs['address'],0,-1);

    $inputs['addresses'] = [$inputs['address']];
}
else
    $inputs['addresses'] = [];
