<?php

if(!isset($executionParameters))
    $executionParameters = [];

$executionParameters['test'] = $test;

$result = $objHandler->updateObject(
    $params['id'],
    $params['content'],
    $params['group'],
    $params['newVRank'],
    $params['newMRank'],
    $params['mainOwner'],
    $params['addOwners'],
    $params['remOwners'],
    $executionParameters
);

