<?php

$categories = $action === 'getCategoryTags';
$validationObject = $categories?$TAGS_INPUT_MAP_DEFINITIONS['getCategoryTags']:$TAGS_INPUT_MAP_DEFINITIONS['getBaseTags'];
$validationObject = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($validationObject,$STANDARD_API_INPUT_MAPS['standardPaginationValidation']);

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

//Auth
if(($inputs['getMeta'] || $inputs['weightTo'] || $inputs['weightFrom']) && !($auth->isAuthorized() || $auth->hasAction($TAGS_API_DEFINITIONS['auth']['getAdminParams']))){
    if($test)
        echo 'Only an admin may get meta or filter by weight!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}