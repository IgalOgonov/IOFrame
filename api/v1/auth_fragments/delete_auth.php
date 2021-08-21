<?php
//Auth check
if(!$auth->isAuthorized(0) && !$auth->hasAction(AUTH_DELETE) && !$auth->hasAction(AUTH_MODIFY) ){
    if($action == 'setActions' && !$auth->hasAction(AUTH_DELETE_ACTIONS) && !$auth->hasAction(AUTH_MODIFY_ACTIONS)){
        if($test)
            echo 'Must be rank 0, or have the relevant actions to delete/modify actions!';
        exit(AUTHENTICATION_FAILURE);
    }
    elseif(!$auth->hasAction(AUTH_DELETE_GROUPS) && !$auth->hasAction(AUTH_MODIFY_GROUPS)){
        if($test)
            echo 'Must be rank 0, or have the relevant actions to delete/modify groups!';
        exit(AUTHENTICATION_FAILURE);
    }
}