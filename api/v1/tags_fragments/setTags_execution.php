<?php


//Parsing
$setInputs = [];
$map = $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap'];
$map = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($map,$categories?$TAGS_PARSING_MAP_DEFINITIONS['setCategoryTags']:$TAGS_PARSING_MAP_DEFINITIONS['setBaseTags']);

foreach ($inputs['tags'] as $item){

    $item['type'] = $inputs['type'];
    if($categories)
        $item['category'] = $inputs['category'];

    $arr = $APIManager::baseItemParser($item,$map);

    if(!empty($arr['Meta']))
        $arr['Meta'] = json_encode($arr['Meta']);
    else
        $arr['Meta'] = null;

    $setInputs[] = $arr;
}

$params = ['test'=>$test,'cacheFullResultsCustomSuffix'=>($categories ? $inputs['type'].'/'.$inputs['category'] : $inputs['type'])];
$params['overwrite'] = $inputs['overwrite'] ?? null;
$params['update'] = $inputs['update'] ?? null;

$result = $TagHandler->setItems(
    $setInputs,
    $inputs['type'],
    $params
);