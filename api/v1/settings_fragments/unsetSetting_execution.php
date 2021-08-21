<?php

require_once 'targetInitiation.php';

$result = $targetSettings->setSetting(
    $params['settingName'],
    null,
    ['test'=>$test,'targetName'=>null,'createNew'=>true]
);
