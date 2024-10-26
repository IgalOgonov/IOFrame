<?php

$authorized = $auth->isAuthorized() || $auth->hasAction('USERS_ORDERS_VIEW_AUTH');
$associated = ($details['ID'] == $inputs['userID']);
if(
    !$authorized &&
    (!$associated || $inputs['getMeta'])
){
    if($test)
        echo 'Only an admin may view all orders / order meta information!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}