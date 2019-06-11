<?php


if(!isset($executionParameters))
    $executionParameters = [];

$executionParameters['test'] = $test;

//Create the object
$result = $objHandler->addObject($obj,$group,$minModifyRank,$minViewRank, $executionParameters);

