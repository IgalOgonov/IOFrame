<?php

$categories = $action === 'getCategoryTags';
unset(
    $STANDARD_API_INPUT_MAPS['standardPaginationValidation']['getMeta'],
    $STANDARD_API_INPUT_MAPS['standardPaginationValidation']['weightFrom'],
    $STANDARD_API_INPUT_MAPS['standardPaginationValidation']['weightTo']
);
$validationObject = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($LANGUAGE_OBJECTS_INPUT_MAP_DEFINITIONS['getLanguageObjects'],$STANDARD_API_INPUT_MAPS['standardPaginationValidation']);

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

//Auth
if( ( empty($languages) || !$inputs['language'] || empty($inputs['names']) ) && !($auth->isAuthorized() || $auth->hasAction($LANGUAGE_OBJECTS_API_DEFINITIONS['auth']['getAdminParams']))){
    if($test)
        echo 'Only an admin may get language object source!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}