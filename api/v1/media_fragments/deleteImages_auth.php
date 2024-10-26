<?php

$ownsAll = false;
//TODO Check which of those items can be modified via individual auth, then check individual auth
foreach($inputs['addresses'] as $index => $address){
}

//Check auth
if(!$ownsAll){
    if( !( $auth->hasAction(IMAGE_DELETE_AUTH) || $auth->isAuthorized() ) ){
        if($test)
            echo 'Cannot delete image!'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }
}