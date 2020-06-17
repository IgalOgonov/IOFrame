<?php
$TokenHandler = new \IOFrame\Handlers\TokenHandler($settings,$defaultSettingsParams);

$result = $TokenHandler->getTokens(
    $inputs['tokens'],
    [
        'tokenLike'=>$inputs['tokenLike'],
        'actionLike'=>$inputs['actionLike'],
        'usesAtLeast'=>$inputs['usesAtLeast'],
        'usesAtMost'=>$inputs['usesAtMost'],
        'expiresBefore'=>$inputs['expiresBefore'],
        'expiresAfter'=>$inputs['expiresAfter'],
        'ignoreExpired'=>$inputs['ignoreExpired'],
        'limit'=>$inputs['limit'],
        'offset'=>$inputs['offset'],
        'test'=>$test
    ]
);

$tempRes = [];

foreach($result as $key => $res){
    if($key === '@'){
        $tempRes[$key] = $res;
        continue;
    }
    $tempRes[$key] = [
        'action' => $res['Token_Action'],
        'uses' => $res['Uses_Left'],
        'expires' => $res['Expires'],
        'locked' => ($res['Session_Lock'] !== null)
    ];
}

$result = $tempRes;


