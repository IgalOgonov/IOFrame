<?php
//If we are passing potentially unsafe inputs, we need to perform special checks
if($inputs['safe']){
    $authCheck = checkAuth([
        'test'=>$test,
        'authRequired' => REQUIRED_AUTH_ADMIN,
        'keys'=>[],
        'objectAuth' => [],
        'actionAuth' => [ARTICLES_BLOCKS_ASSUME_SAFE],
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
}

//Checks
$authCheck = checkAuth([
    'test'=>$test,
    'authRequired' => $requiredAuth,
    'keys'=>[['Article_ID'=>$inputs['id']]],
    'objectAuth' => [OBJECT_AUTH_MODIFY_ACTION],
    'actionAuth' => [ARTICLES_MODIFY_AUTH,ARTICLES_UPDATE_AUTH],
    'levelAuth' => 0,
    'defaultSettingsParams' => $defaultSettingsParams,
    'localSettings' => $settings,
    'AuthHandler' => $auth,
]);

if($authCheck === false || (gettype($authCheck) === 'array' && !empty($authCheck))){
    if($test)
        echo 'Authentication failed!'.EOL;
    die(json_encode([
        'error'=>'Authentication',
        'message'=>'Authentication failed for article!'
    ]));
}