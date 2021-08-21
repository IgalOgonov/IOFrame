<?php

//AUTH
if(!$auth->isLoggedIn()){
    if($test)
        echo "Cannot complete action without being logged in!".EOL;
    exit(AUTHENTICATION_FAILURE);
}

$inputs['id'] = json_decode($_SESSION['details'],true)['ID'];