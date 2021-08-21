<?php

//TODO Check individual auth
if(false){

}
//Check auth
elseif( !( $auth->hasAction(GALLERY_GET_ALL_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot get image galleries image!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}