<?php
require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);

$result = $IPHandler->getIPRanges($inputs['ranges'],['type'=>$inputs['type'],'ignoreExpired'=>$inputs['ignoreExpired'],'limit'=>$inputs['limit'],'offset'=>$inputs['offset'],'test'=>$test]);

$tempRes = [];

foreach($result as $key => $res){
    if($key === '@'){
        $tempRes[$key] = $res;
        continue;
    }
    $tempRes[$key] = [
        'prefix' => $res['Prefix'],
        'from' => (int)$res['IP_From'],
        'to' => (int)$res['IP_To'],
        'type' => (bool)$res['IP_Type'],
        'expires' => (int)$res['Expires']
    ];
};

$result = $tempRes;


