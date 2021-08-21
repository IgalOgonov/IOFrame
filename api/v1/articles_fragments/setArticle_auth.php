<?php

//Convert keys
$authCheck = checkAuth([
    'test'=>$test,
    'authRequired' => $requiredAuth,
    'keys'=>($inputs['create']? [] : [['Article_ID'=>$inputs['articleId']]]),
    'objectAuth' => ($inputs['create']? [] : [OBJECT_AUTH_MODIFY_ACTION]),
    'actionAuth' => ($inputs['create']? [ARTICLES_CREATE_AUTH] : [ARTICLES_MODIFY_AUTH,ARTICLES_UPDATE_AUTH]),
    'levelAuth' => 0,
    'defaultSettingsParams' => $defaultSettingsParams,
    'localSettings' => $settings,
    'AuthHandler' => $auth,
]);

if($authCheck === false || (gettype($authCheck) === 'array' && !empty($authCheck))){
    if($test)
        echo 'Authentication failed!'.EOL;
    die(AUTHENTICATION_FAILURE);
}