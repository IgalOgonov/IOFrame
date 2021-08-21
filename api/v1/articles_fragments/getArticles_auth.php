<?php

if($requiredAuth !== REQUIRED_AUTH_NONE){

    //Convert keys
    $authKeys = [];
    foreach($inputs['keys'] as $key)
        array_push($authKeys,['Article_ID'=>$key]);
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

if($authCheck === false){
    if($test)
        echo 'Authentication failed!'.EOL;
    die(AUTHENTICATION_FAILURE);
}
elseif(gettype($authCheck) === 'array'){

    $keysFailedAuth = [];

    //This is an array of keys that failed the auth - may be empty
    foreach($authCheck as $key => $failedInputIndex){
        unset($inputs['keys'][$failedInputIndex]);
        $keysFailedAuth[$key] = AUTHENTICATION_FAILURE;
    }

    $inputs['keys'] = array_splice($inputs['keys'],0);

    if(count($inputs['keys']) === 0){
        if($test)
            echo 'Authentication failed to view any of the keys!'.EOL;
        die(AUTHENTICATION_FAILURE);
    }
}
