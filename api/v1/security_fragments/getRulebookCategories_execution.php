<?php
$SecurityHandler = new \IOFrame\Handlers\SecurityHandler($settings,$defaultSettingsParams);

$result = $SecurityHandler->getRulebookCategories(['test'=>$test]);

$tempRes = [];

if($result)
    foreach($result as $id =>$arr){
        if(\IOFrame\Util\PureUtilFunctions::is_json($arr['@']))
            $arr['@'] = json_decode($arr['@'],true);
        $tempRes[$id] = [
            'name'=>($arr['@']['name'] ?? null),
            'desc'=>($arr['@']['desc'] ?? null)
        ];
    }

$result = $tempRes;