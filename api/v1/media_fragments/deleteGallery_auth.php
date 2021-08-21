<?php

if(false){
    //TODO Check gallery auth and ownership
}
elseif( !( $auth->hasAction(GALLERY_DELETE_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot delete galleries!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}