<?php
if(!$auth->isAuthorized() && !$auth->hasAction(SET_TOKENS_AUTH)){
    if($test)
        echo 'Cannot set tokens'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

