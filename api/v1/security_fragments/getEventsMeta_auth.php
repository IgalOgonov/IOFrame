<?php
if(!$auth->isAuthorized(0) && !$auth->hasAction(SECURITY_RATE_LIMIT_AUTH)){
    if($test)
        echo 'Cannot get rulebooks meta'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

