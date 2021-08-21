<?php
if(!$auth->isAuthorized(0) && !$auth->hasAction(DELETE_TOKENS_AUTH)){
    if($test)
        echo 'Cannot delete tokens'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
