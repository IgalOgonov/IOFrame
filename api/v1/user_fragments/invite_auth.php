<?php
//AUTH
if ( !( $auth->isAuthorized(0) || $auth->hasAction(INVITE_USERS_AUTH) ) ){
    if($test)
        echo "Cannot invite users!".EOL;
    exit(AUTHENTICATION_FAILURE);
}
//AUTH
if ( $inputs['extraTemplateArguments'] !== null && !( $auth->isAuthorized(0) || $auth->hasAction(SET_INVITE_MAIL_ARGS) ) ){
    if($test)
        echo "Cannot invite users!".EOL;
    exit(AUTHENTICATION_FAILURE);
}
