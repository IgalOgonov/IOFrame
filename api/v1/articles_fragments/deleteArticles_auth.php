<?php

//Convert keys
$authKeys = [];
foreach($inputs['articles'] as $key)
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
    if($test)
        echo 'Authentication failed!'.EOL;
    die(AUTHENTICATION_FAILURE);
}
elseif(gettype($authCheck) === 'array'){

    $articlesFailedAuth = [];

    //This is an array of keys that failed the auth - may be empty
    foreach($authCheck as $key => $failedInputIndex){
        unset($inputs['articles'][$failedInputIndex]);
        $articlesFailedAuth[$key] = AUTHENTICATION_FAILURE;
    }

    $inputs['articles'] = array_splice($inputs['articles'],0);

    if(count($inputs['articles']) === 0){
        if($test)
            echo 'Authentication failed, cannot delete any of the articles!'.EOL;
        die(AUTHENTICATION_FAILURE);
    }
}
