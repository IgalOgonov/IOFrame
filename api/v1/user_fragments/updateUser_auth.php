<?php
//AUTH
if (!$auth->isAuthorized() && !$auth->hasAction(SET_USERS_AUTH) ){
    if($test)
        echo "User must be authorized to update users".EOL;
    exit(AUTHENTICATION_FAILURE);
}