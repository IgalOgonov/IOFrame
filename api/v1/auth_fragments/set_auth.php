<?php
//Auth check
if(!$auth->isAuthorized(0) && !$auth->hasAction(AUTH_SET) ){
    if($action == 'setActions' && !$auth->hasAction(AUTH_SET_ACTIONS)){
        if($test)
            echo 'Must be rank 0, or have the relevant actions to modify actions!';
        exit(AUTHENTICATION_FAILURE);
    }
    elseif(!$auth->hasAction(AUTH_SET_GROUPS)){
        if($test)
            echo 'Must be rank 0, or have the relevant actions to modify groups!';
        exit(AUTHENTICATION_FAILURE);
    }
}
