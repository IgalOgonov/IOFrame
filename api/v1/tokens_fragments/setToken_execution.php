<?php
$TokenHandler = new \IOFrame\Handlers\TokenHandler($settings,$defaultSettingsParams);

$result = $TokenHandler->setToken(
    $inputs['token'],
    $inputs['tokenAction'],
    (($inputs['uses'] === null) && !$inputs['update'])? 1 : $inputs['uses'],
    (($inputs['ttl'] === null) && !$inputs['update'])? -1 : $inputs['ttl'],
    (($inputs['tags'] === null) && !$inputs['update'])? [] : $inputs['tags'],
    [
        'test'=>$test,
        'overwrite'=>$inputs['overwrite'],
        'update'=>$inputs['update']
    ]
);