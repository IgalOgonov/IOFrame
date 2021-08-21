<?php

$params = ["contactType"=>$inputs['contactType'],
    "firstNameLike"=>$inputs['firstNameLike'],
    "emailLike"=>$inputs['emailLike'],
    "countryLike"=>$inputs['countryLike'],
    "cityLike"=>$inputs['cityLike'],
    "companyNameLike"=>$inputs['companyNameLike'],
    "createdBefore"=>$inputs['createdBefore'],
    "createdAfter"=>$inputs['createdAfter'],
    "changedBefore"=>$inputs['changedBefore'],
    "changedAfter"=>$inputs['changedAfter'],
    "includeRegex"=>$inputs['includeRegex'],
    "excludeRegex"=>$inputs['excludeRegex'],
    "fullNameLike"=>$inputs['fullNameLike'],
    "companyNameIDLike"=>$inputs['companyNameIDLike'],
    "orderBy"=>$inputs['orderBy'],
    "orderType"=>$inputs['orderType'],
    "limit"=>$inputs['limit'],
    "offset"=>$inputs['offset'],
    'test'=>$test
];

if(isset($extraDBFilters))
    $params['extraDBFilters'] = $extraDBFilters;
if(isset($extraCacheFilters))
    $params['extraCacheFilters'] = $extraCacheFilters;

$result = $ContactHandler->getContacts([],$params);

$tempRes = [];

if($result){
    foreach($result as $identifier => $contactArr){
        if($identifier === '@'){
            $tempRes[$identifier] = $contactArr;
            continue;
        }
        $tempRes[$identifier] = [];
        foreach($translationTable as $dbName => $resArr){
            $tempRes[$identifier][$resArr['newName']] = isset($contactArr[$dbName])? $contactArr[$dbName] : null;
            if(isset($resArr['newName']['isJson']) && $resArr['newName']['isJson'] && \IOFrame\Util\is_json($tempRes[$identifier][$resArr['newName']]))
                $tempRes[$identifier][$resArr['newName']] = json_decode($tempRes[$identifier][$resArr['newName']],true);
        }
    }
}

$result = $tempRes;