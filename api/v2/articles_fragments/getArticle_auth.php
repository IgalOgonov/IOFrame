<?php

if($requiredAuth !== REQUIRED_AUTH_NONE){
    //Convert keys
    $authKeys = !empty($inputs['id'])? [['Article_ID'=>$inputs['id']]] : [['Article_Address'=>$inputs['address']]];
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
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE,'message'=>'Authentication failed'],!$test);
}
