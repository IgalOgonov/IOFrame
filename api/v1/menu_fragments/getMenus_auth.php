<?php

if(!$auth->isAuthorized()){
    if($test)
        echo 'Only an admin may view all menus!!'.EOL;
    die(AUTHENTICATION_FAILURE);
}