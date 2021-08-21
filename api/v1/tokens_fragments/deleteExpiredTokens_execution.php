<?php
$TokenHandler = new \IOFrame\Handlers\TokenHandler($settings,$defaultSettingsParams);

$result = $TokenHandler->deleteExpiredTokens(
    [
        'time'=>$inputs['time'],
        'test'=>$test
    ]
);