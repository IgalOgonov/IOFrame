<?php
require __DIR__ . '/../../IOFrame/Handlers/IPHandler.php';
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);

$result = $IPHandler->getIPs($inputs['ips'],['type'=>$inputs['type'],'reliable'=>$inputs['reliable'],'ignoreExpired'=>$inputs['ignoreExpired'],'limit'=>$inputs['limit'],'offset'=>$inputs['offset'],'test'=>$test]);

$tempRes = [];

foreach($result as $key => $res){
    if($key === '@'){
        $tempRes[$key] = $res;
        continue;
    }
    $tempRes[$key] = [
         'IP' => $res['IP'],
         'reliable' => $res['Is_Reliable'],
         'type' => $res['IP_Type'],
         'expires' => $res['Expires']
    ];
}

$result = $tempRes;


