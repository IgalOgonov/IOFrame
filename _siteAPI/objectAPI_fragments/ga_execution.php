<?php


//Get page path
$page = $params['page'];
//Get Date
$date = $params['date'];
//Get the objects assigned to the page
$result = $objHandler->getObjectMap($page,$date,$test);

