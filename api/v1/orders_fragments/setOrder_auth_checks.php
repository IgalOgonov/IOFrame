<?php

if(!$auth->isAuthorized() && !$auth->hasAction(ORDERS_MODIFY_AUTH)){
    if($test)
        echo 'Only an admin may modify orders directly!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}