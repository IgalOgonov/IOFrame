<?php

//Auth
if(!$auth->isAuthorized() && !$auth->hasAction(MAILS_GET_TEMPLATE)){
    if($test)
        echo 'Only the system admin may get templates!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
