<?php
//AUTH
if (!$auth->isAuthorized(0) && !$auth->hasAction(GET_USERS_AUTH) ){
    if($test)
        echo "User must be authorized to get users".EOL;
    exit(AUTHENTICATION_FAILURE);
}
