<?php

if(!$auth->isAuthorized(0)){
    if($test)
        echo 'Only the system admin may archive orders!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}