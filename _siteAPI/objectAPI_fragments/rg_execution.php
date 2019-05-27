<?php



//Group to get
$groupName = $params['groupName'];
//TODO CONVERT TO SAFE STRING
//Optional parameters
isset($params['updated'])?
    $updated = $params['updated'] : $updated = 0;

//Meant for extension by plugins
if(!isset($executionParameters))
    $executionParameters = [];

$executionParameters['updated'] = $updated;
$executionParameters['test'] = $test;

//Get all the objects requested
$result =  $objHandler->getObjectsByGroup($groupName,$executionParameters);