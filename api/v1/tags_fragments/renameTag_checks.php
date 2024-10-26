<?php

$categories = $action === 'renameCategoryTags';
$validationObject = $categories?$TAGS_INPUT_MAP_DEFINITIONS['renameCategoryTags']:$TAGS_INPUT_MAP_DEFINITIONS['renameBaseTags'];
$validationObject = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($validationObject,$TAGS_INPUT_MAP_COMPONENT_DEFINITIONS['standardRenameValidation']);

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

//Auth
if( !($auth->isAuthorized() || $auth->hasAction($TAGS_API_DEFINITIONS['auth']['set'])) ){
    if($test)
        echo 'Only an admin may rename tags!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}