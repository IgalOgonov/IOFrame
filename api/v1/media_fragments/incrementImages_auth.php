<?php

//Check auth
if( !( $auth->hasAction(IMAGE_INCREMENT_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot increment image!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}