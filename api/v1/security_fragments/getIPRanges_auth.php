<?php
if(!$auth->isAuthorized(0) && !$auth->hasAction(SECURITY_IP_AUTH) && !$auth->hasAction(SECURITY_IP_VIEW)){
    if($test)
        echo 'Cannot get view IP rules'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
