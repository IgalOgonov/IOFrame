<?php
if(!$auth->isAuthorized(0) && !$auth->hasAction(SET_TOKENS_AUTH)){
    if($test)
        echo 'Cannot set tokens'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
