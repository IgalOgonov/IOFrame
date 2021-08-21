<?php
if( !( $auth->hasAction(MEDIA_FOLDER_CREATE_AUTH) || $auth->isAuthorized(0) ) ){
    if($test)
        echo 'Cannot create media folders!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}