<?php

//$tagSettings defined in definitions already

if($inputs['updatedAfter']){
    $updated = $tagSettings->getLastUpdateTimes('tagSettings');
    if($updated == '-1'){
        if($test)
            echo 'unexpected error'.EOL;
        die('-1');
    }
    elseif($inputs['updatedAfter']>(int)$updated)
        die('0');
}

$currentTags = $tagSettings->getSetting('availableTagTypes');
$currentCategoryTags = $tagSettings->getSetting('availableCategoryTagTypes');

$result = [
    'availableTagTypes'=>\IOFrame\Util\PureUtilFunctions::is_json($currentTags)?json_decode($currentTags,true):null,
    'availableCategoryTagTypes'=>\IOFrame\Util\PureUtilFunctions::is_json($currentCategoryTags)?json_decode($currentCategoryTags,true):null,
];