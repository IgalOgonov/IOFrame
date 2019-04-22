<?php



//Group to get
$groupName = $params['groupName'];
//TODO CONVERT TO SAFE STRING
//Optional parameters
isset($params['updated'])?
    $updated = $params['updated'] : $updated = 0;

//Get all the objects requested
$objects =  $objHandler->getObjectsByGroup($groupName,$updated,true,true,$test);
//Parse content if needed
if(function_exists('parseObjectContent')){
    foreach($objects as $id=>$content){
        if(gettype($id)!='integer'){
            $objects[$id] = parseObjectContent($content);
        }
    }
}


$result = $objects;
