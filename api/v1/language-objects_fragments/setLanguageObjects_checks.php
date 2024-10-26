<?php

$validationObject = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct(
    $LANGUAGE_OBJECTS_INPUT_MAP_DEFINITIONS['setLanguageObjects'],
    $STANDARD_API_INPUT_MAPS['standardUpdateOverwriteValidation']
);

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test,'nameRegex'=>$LANGUAGE_OBJECTS_API_DEFINITIONS['validation']['name']]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

//Auth
if( !($auth->isAuthorized() || $auth->hasAction($LANGUAGE_OBJECTS_API_DEFINITIONS['auth']['set'])) ){
    if($test)
        echo 'Only an admin may set language objects!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}