<?php

if(!$auth->isAuthorized()){
    if($test)
        echo 'Only an admin may modify menus!!'.EOL;
    die(AUTHENTICATION_FAILURE);
}