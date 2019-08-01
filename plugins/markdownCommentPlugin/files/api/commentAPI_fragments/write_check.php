<?php

if(isset($params['trusted'])){
    if(! ( $auth->isAuthorized(0) || $auth->hasAction('MAKE_TRUSTED_COMMENTS') ) ){
        if($test)
            echo 'User lacks the needed authorization to make trusted comments!'.EOL;
        exit('-1');
    }

    if($params['trusted'] == '0' || !$params['trusted'])
        $params['trusted'] = 0;
    else
        $params['trusted'] = 1;
}














