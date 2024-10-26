<?php


if( !( $auth->hasAction(IMAGE_UPLOAD_AUTH) || $auth->isAuthorized() ) ){
    if($test)
        echo 'Cannot upload images'.EOL;
    exit(AUTHENTICATION_FAILURE);
}


