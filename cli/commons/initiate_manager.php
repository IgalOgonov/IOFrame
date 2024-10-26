<?php

$CLIManager = new \IOFrame\Managers\CLIManager($settings);

$initiation = $CLIManager->initiate(
    $initiationDefinitions,
    $initiationParams
);

$result = [
    'result'=>null,
    'error'=>null
];