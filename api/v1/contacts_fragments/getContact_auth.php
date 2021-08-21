<?php

//Auth check
if(!$auth->isAuthorized(0) && !$auth->hasAction('GET_CONTACTS')){
    if($test)
        echo 'Must be rank 0, or have the relevant action!';
    exit(AUTHENTICATION_FAILURE);
}