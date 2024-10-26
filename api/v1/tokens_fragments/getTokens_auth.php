<?php
if(!$auth->isAuthorized() && !$auth->hasAction(GET_TOKENS_AUTH)){
    if($test)
        echo 'Cannot get tokens'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
