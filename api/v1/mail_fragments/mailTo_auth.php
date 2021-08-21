<?php
//Auth
$res = $MailHandler->verifySecToken($inputs['secToken'],$inputs['mail']);
if($res !=0){
    if($test)
        echo 'Security token does not allow this action, error '.$res.EOL;
    exit(AUTHENTICATION_FAILURE);
}

