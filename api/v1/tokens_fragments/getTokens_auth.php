<?php
if(!$auth->isAuthorized(0) && !$auth->hasAction(GET_TOKENS_AUTH)){
    if($test)
        echo 'Cannot get tokens'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
