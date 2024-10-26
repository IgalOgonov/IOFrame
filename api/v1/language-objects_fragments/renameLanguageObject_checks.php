<?php

$validationObject = $LANGUAGE_OBJECTS_INPUT_MAP_DEFINITIONS['renameLanguageObjects'];

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

//Auth
if( !($auth->isAuthorized() || $auth->hasAction($LANGUAGE_OBJECTS_API_DEFINITIONS['auth']['set'])) ){
    if($test)
        echo 'Only an admin may rename items!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}