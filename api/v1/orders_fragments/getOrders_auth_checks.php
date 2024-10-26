<?php

if(!$auth->isAuthorized() && !$auth->hasAction(ORDERS_VIEW_AUTH)){
    if($test)
        echo 'Only an admin may view all orders!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}