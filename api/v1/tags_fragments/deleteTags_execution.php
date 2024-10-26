<?php




//Parsing
$deleteItems = [];
$map = $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['deleteMap'];
if($categories)
    $map = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($map,$TAGS_PARSING_MAP_DEFINITIONS['deleteCategoryTags']);

foreach($inputs['identifiers'] as $identifier){

    $item['type'] = $inputs['type'];
    $item['identifier'] = $identifier;
    if($categories)
        $item['category'] = $inputs['category'];
    $arr = $APIManager::baseItemParser($item,$map);
    $deleteItems[] = $arr;
}

//The columns to get only differ between category and base tags, not amongst themselves
$result = $TagHandler->deleteItems(
    $deleteItems,
    $inputs['type'],
    ['test'=>$test,'cacheFullResultsCustomSuffix'=>($categories ? $inputs['type'].'/'.$inputs['category'] : $inputs['type'])]
);