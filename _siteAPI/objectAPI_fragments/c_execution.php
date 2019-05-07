<?php


if(!isset($executionParameters))
    $executionParameters = [];

//Create the object
$result = $objHandler->addObject($obj,$group,$minModifyRank,$minViewRank, $executionParameters, $test);

