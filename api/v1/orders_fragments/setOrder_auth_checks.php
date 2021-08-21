<?php

if(!$auth->isAuthorized(0) && !$auth->hasAction(ORDERS_MODIFY_AUTH)){
    if($test)
        echo 'Only an admin may modify orders directly!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}