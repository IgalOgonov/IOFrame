<?php
if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

//Check auth
if(false){
    //TODO Check gallery auth and ownership, THEN image ones
}
elseif( !( $auth->hasAction(GALLERY_UPDATE_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot modify galleries, or their memberships!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
