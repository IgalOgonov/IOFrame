<?php

//Auth check
if(!$auth->isAuthorized() && !$auth->hasAction(AUTH_MODIFY) ){
    if( ($action == 'modifyUserActions' || $action == 'modifyUserGroups' ) && !$auth->hasAction(AUTH_MODIFY_USERS)){
        if($test)
            echo 'Must be rank 0, or have the relevant actions to modify users!';
        exit(AUTHENTICATION_FAILURE);
    }
    elseif(!$auth->hasAction('AUTH_MODIFY_GROUPS')){
        if($test)
            echo 'Must be rank 0, or have the relevant actions to modify groups!';
        exit(AUTHENTICATION_FAILURE);
    }
}
