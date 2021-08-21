<?php
if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

if( !( $auth->hasAction(IMAGE_UPLOAD_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot upload images'.EOL;
    exit(AUTHENTICATION_FAILURE);
}


