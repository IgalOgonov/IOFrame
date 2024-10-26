<?php
if(!$auth->isAuthorized() && !$auth->hasAction(SECURITY_IP_AUTH)){
    if($test)
        echo 'Cannot delete expired IP rules'.EOL;
    exit(AUTHENTICATION_FAILURE);
}