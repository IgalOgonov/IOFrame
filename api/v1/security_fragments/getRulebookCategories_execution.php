<?php
require __DIR__ . '/../../../IOFrame/Handlers/SecurityHandler.php';
$SecurityHandler = new \IOFrame\Handlers\SecurityHandler($settings,$defaultSettingsParams);

$result = $SecurityHandler->getRulebookCategories(['test'=>$test]);

$tempRes = [];

if($result)
    foreach($result as $id =>$arr){
        if(\IOFrame\Util\is_json($arr['@']))
            $arr['@'] = json_decode($arr['@'],true);
        $tempRes[$id] = [
            'name'=>(isset($arr['@']['name']) ? $arr['@']['name'] : null),
            'desc'=>(isset($arr['@']['desc']) ? $arr['@']['desc'] : null)
        ];
    }

$result = $tempRes;