<?php
//AUTH
if(!$auth->isLoggedIn()){
    if($test)
        echo "Cannot complete action without being logged in!".EOL;
    exit(AUTHENTICATION_FAILURE);
}

if ($inputs['id']!==null && !$auth->isAuthorized(0) && !$auth->hasAction(SET_USERS_AUTH) ){
    if($test)
        echo "User must be authorized to update other users".EOL;
    exit(AUTHENTICATION_FAILURE);
}

if($inputs['id'] === null && !empty($inputs['require2FA'])){
    if(!( $auth->getDetail('TwoFactorAppReady') || $auth->getDetail('Phone') /*|| TODO add condition to check mail settings when 2FA by mail is done*/))
        die('NO_SUPPORTED_2FA');
}