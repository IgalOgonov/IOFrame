<?php

if( !( $auth->hasAction(GALLERY_GET_ALL_AUTH) || $auth->isAuthorized() ) ){
    if($test)
        echo 'Cannot get all galleries'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
