<?php
if(!$auth->isAuthorized() && !$auth->hasAction(SECURITY_RATE_LIMIT_AUTH)){
    if($test)
        echo 'Cannot get rulebook categories'.EOL;
    exit(AUTHENTICATION_FAILURE);
}