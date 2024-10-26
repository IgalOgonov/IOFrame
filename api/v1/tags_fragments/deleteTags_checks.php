<?php

$categories = $action === 'deleteCategoryTags';
$validationObject = $categories?$TAGS_INPUT_MAP_DEFINITIONS['deleteCategoryTags']:$TAGS_INPUT_MAP_DEFINITIONS['deleteBaseTags'];
$validationObject = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($validationObject,$STANDARD_API_INPUT_MAPS['standardUpdateOverwriteValidation']);

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

//Auth
if( !($auth->isAuthorized() || $auth->hasAction($TAGS_API_DEFINITIONS['auth']['delete'])) ){
    if($test)
        echo 'Only an admin may delete tags!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}