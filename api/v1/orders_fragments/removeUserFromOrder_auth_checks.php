<?php

if(!$auth->isAuthorized() && !$auth->hasAction('USERS_ORDERS_MODIFY_AUTH')){
    if($test)
        echo 'Only an admin may modify user-order relations directly!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}