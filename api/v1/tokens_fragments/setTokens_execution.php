<?php
$TokenHandler = new \IOFrame\Handlers\TokenHandler($settings,$defaultSettingsParams);

$result = $TokenHandler->setTokens(
    $inputs['tokens'],
    [
        'overwrite'=>$inputs['overwrite'],
        'update'=>$inputs['update'],
        'test'=>$test
    ]
);