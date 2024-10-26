<?php
//AUTH
if ( !( $auth->isAuthorized() || $auth->hasAction(INVITE_USERS_AUTH) ) ){
    if($test)
        echo "Cannot invite users!".EOL;
    exit(AUTHENTICATION_FAILURE);
}
//AUTH
if ( $inputs['extraTemplateArguments'] !== null && !( $auth->isAuthorized() || $auth->hasAction(SET_INVITE_MAIL_ARGS) ) ){
    if($test)
        echo "Cannot invite users!".EOL;
    exit(AUTHENTICATION_FAILURE);
}
