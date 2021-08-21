<?php

//Auth
if(!$auth->isAuthorized(0) && !$auth->hasAction(MAILS_MODIFY_TEMPLATE)){
    if($test)
        echo 'Only the system admin may modify templates!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
