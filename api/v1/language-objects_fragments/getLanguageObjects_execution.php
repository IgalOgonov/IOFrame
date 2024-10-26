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

if(empty($inputs['names'])){
    $result = $LanguageObjectHandler->getLanguageObjects(
        [],
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
            'load'=>true,
        ]
    );

    if(is_array($result)){
        $tempRes = [];
        $map = array_merge(
            $STANDARD_API_PARSING_MAPS['standardTimeColumns'],
            $LANGUAGE_OBJECTS_PARSING_MAP_COMPONENT_DEFINITIONS['standardLanguageObjectMap']
        );

        foreach ($result as $key=>$res){
            $temp = [];
            if(!is_array($res) || ($key === '@'))
                $temp = $res;
            else{
                $temp = $APIManager::baseItemParser($res,$map);
            }
            $tempRes[$key] = $temp;
        }

        $result = $tempRes;
    }
}
else{
    $result = $LanguageObjectHandler->getLoadedObjects(
        $inputs['names'],
        [
            'test'=>$test,
            'language'=>$inputs['language']?? null,
            'load'=>true,
        ]
    );
}