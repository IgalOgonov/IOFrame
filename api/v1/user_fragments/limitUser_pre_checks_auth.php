<?php

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Cannot limit users if you\'re not even logged in!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

if( !( $auth->isAuthorized() || $auth->hasAction(BAN_USERS_AUTH) ) ){
    if($test)
        echo 'Insufficient auth to limit users!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

