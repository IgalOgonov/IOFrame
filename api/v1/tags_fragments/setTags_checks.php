<?php

$categories = $action === 'setCategoryTags';
$validationObject = $categories?$TAGS_INPUT_MAP_DEFINITIONS['setCategoryTags']:$TAGS_INPUT_MAP_DEFINITIONS['setBaseTags'];
$validationObject = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct(
    $validationObject,
    $TAGS_INPUT_MAP_COMPONENT_DEFINITIONS['standardSetValidation'],
    $STANDARD_API_INPUT_MAPS['standardUpdateOverwriteValidation']
);

//Validation
$validation = $APIManager::baseValidation(
    $inputs,
    $validationObject,
    ['test'=>$test]
);

if(!$validation['passed'])
    exit(INPUT_VALIDATION_FAILURE);

foreach ($inputs['tags'] as $index => $tag){
    $validation = $APIManager::baseValidation(
        $inputs['tags'][$index],
        $TAGS_INPUT_MAP_DEFINITIONS['setTags'],
        ['test'=>$test]
    );
    if(!$validation['passed'])
        exit(INPUT_VALIDATION_FAILURE);
}

//Auth
if( !($auth->isAuthorized() || $auth->hasAction($TAGS_API_DEFINITIONS['auth']['set'])) ){
    if($test)
        echo 'Only an admin may set tags!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}