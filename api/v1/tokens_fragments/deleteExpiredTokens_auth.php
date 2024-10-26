<?php
if(!$auth->isAuthorized() && !$auth->hasAction(DELETE_TOKENS_AUTH)){
    if($test)
        echo 'Cannot delete tokens'.EOL;
    exit(AUTHENTICATION_FAILURE);
}