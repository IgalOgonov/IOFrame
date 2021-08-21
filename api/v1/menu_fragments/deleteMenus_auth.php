<?php

if(!$auth->isAuthorized(0)){
    if($test)
        echo 'Only an admin may modify menus!!'.EOL;
    die(AUTHENTICATION_FAILURE);
}