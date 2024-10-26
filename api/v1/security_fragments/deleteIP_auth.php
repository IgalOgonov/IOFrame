<?php
if(!$auth->isAuthorized() && !$auth->hasAction(SECURITY_IP_AUTH) && !$auth->hasAction(SECURITY_IP_MODIFY)){
    if($test)
        echo 'Cannot modify IP rules'.EOL;
    exit(AUTHENTICATION_FAILURE);
}