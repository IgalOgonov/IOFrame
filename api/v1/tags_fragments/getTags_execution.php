<?php

$type = $inputs['type']??null;
$category = $inputs['category']??null;
$typyCategory = $type && $category ? [$type,$category] : [];

//See if we can use cache
$realFilters = $standardPaginationInputs;
if($categories)
    $realFilters = array_merge($realFilters,["type","category"]);

$noFilters = true;
foreach($realFilters as $filter)
    if($inputs[$filter]??null)
        $noFilters = false;

if($noFilters && ( ($categories ? count($typyCategory) === 2 : $type) ) ){
    $customSuffix = ($categories ? $typyCategory[0].'/'.$typyCategory[1] : $type);
}

//The columns to get only differ between category and base tags, not amongst themselves
$result = $TagHandler->getItems(
    [],
    $type ,
    [
        'test'=>$test,
        'limit'=>$inputs['limit'],
        'offset'=>$inputs['offset'],
        'includeRegex'=>$inputs['includeRegex'],
        'excludeRegex'=>$inputs['excludeRegex'],
        'createdAfter'=>$inputs['createdAfter'],
        'createdBefore'=>$inputs['createdBefore'],
        'changedAfter'=>$inputs['changedAfter'],
        'changedBefore'=>$inputs['changedBefore'],
        'weightFrom'=>$inputs['weightFrom'],
        'weightTo'=>$inputs['weightTo'],
        'typeIs'=>$inputs['type']??null,
        'categoryIs'=>$inputs['category']??null,
        'cacheFullResultsCustomSuffix'=>$customSuffix??null
    ]
);
//Parsing
if(is_array($result)){
    $tempRes = [];
    $map = array_merge(
        $STANDARD_API_PARSING_MAPS['standardTimeColumns'],
        $STANDARD_API_PARSING_MAPS['standardWeightColumn'],
        $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['standardTagMap'],
        $categories? $TAGS_PARSING_MAP_DEFINITIONS['getCategoryTags'] : $TAGS_PARSING_MAP_DEFINITIONS['getBaseTags']
    );

    foreach($result as $index=>$res){
        if($index === '@'){
            $tempRes[$index] = $res;
        }
        else{
            $tempRes[$index] = $APIManager::baseItemParser($res,$map);
            if(!$inputs['getMeta']){
                unset($tempRes[$index]['created']);
                unset($tempRes[$index]['updated']);
            }
        }
    }

    $result = $tempRes;
}