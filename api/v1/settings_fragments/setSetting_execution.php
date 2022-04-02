<?php

require_once 'targetInitiation.php';

$result = $targetSettings->setSetting(
    $params['settingName'],
    $params['settingValue'],
    ['test'=>$test,'targetName'=>null,'createNew'=>$params['createNew']]
);
