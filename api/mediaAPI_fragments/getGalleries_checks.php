<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

if( !( $auth->hasAction(GALLERY_GET_ALL_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot get all galleries'.EOL;
    exit(AUTHENTICATION_FAILURE);
}