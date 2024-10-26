<?php

//Auth check
if(!$auth->isAuthorized() && !$auth->hasAction(OBJECT_AUTH_MODIFY) ){
    if($test)
        echo 'Must be rank 0, or have the relevant actions to modify items!';
    exit(AUTHENTICATION_FAILURE);
}
