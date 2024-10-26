<?php

//Convert keys
$authKeys = [];
foreach($inputs['ids'] as $key)
    $authKeys[] = ['Article_ID' => $key];
$authCheck = checkAuth([
    'test'=>$test,
    'authRequired' => $requiredAuth,
    'keys'=>$authKeys,
    'objectAuth' => [OBJECT_AUTH_MODIFY_ACTION],
    'actionAuth' => [ARTICLES_MODIFY_AUTH,ARTICLES_DELETE_AUTH],
    'levelAuth' => 0,
    'defaultSettingsParams' => $defaultSettingsParams,
    'localSettings' => $settings,
    'AuthHandler' => $auth,
]);

if($authCheck === false){
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE,'message'=>'Authentication failed'],!$test);
}
elseif(gettype($authCheck) === 'array'){

    $articlesFailedAuth = [];

    //This is an array of keys that failed the auth - may be empty
    foreach($authCheck as $key => $failedInputIndex){
        unset($inputs['ids'][$failedInputIndex]);
        $articlesFailedAuth[$key] = AUTHENTICATION_FAILURE;
    }

    $inputs['ids'] = array_splice($inputs['ids'],0);

    if(count($inputs['ids']) === 0){
        if($test)
            echo 'Authentication failed, cannot delete any of the articles!'.EOL;
        die(json_encode([
            'error'=>AUTHENTICATION_FAILURE,
            'message'=>'Authentication failed for all of the keys!'
        ]));
    }
}
