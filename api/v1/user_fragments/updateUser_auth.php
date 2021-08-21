<?php
//AUTH
if (!$auth->isAuthorized(0) && !$auth->hasAction(SET_USERS_AUTH) ){
    if($test)
        echo "User must be authorized to update users".EOL;
    exit(AUTHENTICATION_FAILURE);
}