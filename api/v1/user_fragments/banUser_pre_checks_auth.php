<?php

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Cannot ban users if you\'re not even logged in!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

if( !( $auth->isAuthorized(0) || $auth->hasAction(BAN_USERS_AUTH) ) ){
    if($test)
        echo 'Insufficient auth to ban users!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

