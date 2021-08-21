<?php

if($requiredAuth !== REQUIRED_AUTH_NONE){
    //Convert keys
    $authKeys = ($inputs['id'] !== null)? [['Article_ID'=>$inputs['id']]] : [['Article_Address'=>$inputs['articleAddress']]];
    $authCheck = checkAuth([
        'test'=>$test,
        'authRequired' => $requiredAuth,
        'keys'=>$authKeys,
        'objectAuth' => [OBJECT_AUTH_VIEW_ACTION,OBJECT_AUTH_MODIFY_ACTION],
        'actionAuth' => [ARTICLES_MODIFY_AUTH,ARTICLES_VIEW_AUTH],
        'levelAuth' => 0,
        'defaultSettingsParams' => $defaultSettingsParams,
        'localSettings' => $settings,
        'AuthHandler' => $auth,
    ]);
}
else
    $authCheck = true;

//Only way we can fail is if we failed to check the DB, or we did and the one key we checked returned as unauthorized
if($authCheck === false || (gettype($authCheck) === 'array' && !empty($authCheck))){
    if($test)
        echo 'Authentication failed!'.EOL;
    die(AUTHENTICATION_FAILURE);
}
