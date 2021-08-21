<?php
if(!$auth->isAuthorized(0) && !$auth->hasAction(SECURITY_IP_AUTH)){
    if($test)
        echo 'Cannot delete expired IP rules'.EOL;
    exit(AUTHENTICATION_FAILURE);
}