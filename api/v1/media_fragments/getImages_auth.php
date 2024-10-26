<?php

if($inputs['address'] === null){
    //Only check this auth if we're getting all images
    if( !( $auth->hasAction(IMAGE_GET_ALL_AUTH) || $auth->isAuthorized() ) ){
        if($test)
            echo 'Cannot get all images'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }
}
//TODO Check individual image auth
else{
}