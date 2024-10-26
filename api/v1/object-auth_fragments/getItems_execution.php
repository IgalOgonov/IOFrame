<?php
$ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($settings,$defaultSettingsParams);

$result = $ObjectAuthHandler->getItems($inputs['keys'], $type, $retrieveParams);

foreach($result as $key=>$res){
    if($key === '@'){
        $tempResult[$key] = $res;
        continue;
    }
    if(!is_array($res))
        continue;
    foreach($res as $resKey => $resValue){
        if(!is_array($resValue)){
            $result[$key][$resultsColumnMap[$resKey]] = $resValue;
            unset($result[$key][$resKey]);
        }
        else{
            foreach($resValue as $resSubKey => $resSubValue){
                $result[$key][$resKey][$resultsColumnMap[$resSubKey]] = $resSubValue;
                unset($result[$key][$resKey][$resSubKey]);
            }
        }
    }
}
