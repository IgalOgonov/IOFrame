<?php


//Get page path
$maps = $params['maps'];
//Get the objects assigned to the page
$result = $objHandler->getObjectMaps($maps,['test'=>$test]);

