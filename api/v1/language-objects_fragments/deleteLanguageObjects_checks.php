<?php

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $LANGUAGE_OBJECTS_INPUT_MAP_DEFINITIONS['deleteBaseLanguageObjects'],
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

//Auth
if( !($auth->isAuthorized() || $auth->hasAction($LANGUAGE_OBJECTS_API_DEFINITIONS['auth']['delete'])) ){
    if($test)
        echo 'Only an admin may delete items!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}