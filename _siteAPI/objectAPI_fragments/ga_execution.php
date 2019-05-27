<?php


//Get page path
$pages = $params['pages'];
//Get Date
$date = $params['date'];
//Get the objects assigned to the page
$result = $objHandler->getObjectMaps($pages,['test'=>$test]);

