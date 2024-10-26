<?php

//Check auth
if( !( $auth->hasAction(IMAGE_INCREMENT_AUTH) || $auth->isAuthorized() ) ){
    if($test)
        echo 'Cannot increment image!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}