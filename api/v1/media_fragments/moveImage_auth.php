<?php

//TODO Check whether this item can be modified via individual auth, then check individual auth
if(false){

}
//Check auth
elseif( !( $auth->hasAction(IMAGE_MOVE_AUTH) || $auth->isAuthorized() ) ){
    if($test)
        echo 'Cannot move image!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}