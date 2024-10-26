<?php

//Auth
if(!$auth->isLoggedIn() || !$auth->getDetail('ID')){
    if($test)
        echo 'Must be logged in to set preferred language!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

$validationObject = $LANGUAGE_OBJECTS_INPUT_MAP_DEFINITIONS['setPreferredLanguage'];

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);