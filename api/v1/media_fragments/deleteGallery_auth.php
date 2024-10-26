<?php

//TODO Check gallery auth and ownership
if(false){
}
elseif( !( $auth->hasAction(GALLERY_DELETE_AUTH) || $auth->isAuthorized() ) ){
    if($test)
        echo 'Cannot delete galleries!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}