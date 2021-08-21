<?php
$TokenHandler = new \IOFrame\Handlers\TokenHandler($settings,$defaultSettingsParams);

$result = $TokenHandler->deleteTokens(
    $inputs['tokens'],
    ['test'=>$test]
);