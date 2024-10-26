<?php

if(!$auth->isAuthorized()){
    if($test)
        echo 'Only the system admin may archive orders!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}