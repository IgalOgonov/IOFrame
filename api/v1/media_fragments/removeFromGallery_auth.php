<?php


//Check auth
//TODO Check gallery auth and ownership
if(false){
}
elseif( !( $auth->hasAction(GALLERY_UPDATE_AUTH) || $auth->isAuthorized() ) ){
    if($test)
        echo 'Cannot modify galleries, or their memberships!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}