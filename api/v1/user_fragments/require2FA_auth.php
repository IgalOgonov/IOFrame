<?php
//AUTH
if(!$auth->isLoggedIn()){
    if($test)
        echo "Cannot complete action without being logged in!".EOL;
    exit(AUTHENTICATION_FAILURE);
}

if ($inputs['id']!==null && !$auth->isAuthorized() && !$auth->hasAction(SET_USERS_AUTH) ){
    if($test)
        echo "User must be authorized to update other users".EOL;
    exit(AUTHENTICATION_FAILURE);
}