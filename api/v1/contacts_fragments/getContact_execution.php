<?php

$params = [
    'test'=>$test
];

$result = $ContactHandler->getContact($inputs['id'],$params);

$tempRes = [];

if($result){
    foreach($translationTable as $dbName => $resArr){
        $tempRes[$resArr['newName']] = $result[$dbName] ?? null;
        if(isset($resArr['isJson']) && $resArr['isJson'] && \IOFrame\Util\PureUtilFunctions::is_json($tempRes[$resArr['newName']]))
            $tempRes[$resArr['newName']] = json_decode($tempRes[$resArr['newName']],true);
    }
}

$result = $tempRes;