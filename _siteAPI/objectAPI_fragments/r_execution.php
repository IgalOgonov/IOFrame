<?php



$arr = [];          //Array of objects we need to get.
$checkUpdatedGroups = [];       //Groups we need to check for being updated

foreach($params as $groupName=>$value){
    if($groupName != '@'){
    $objArr = json_decode($value,true);
    //Check if the group is up to date, in which case no need to check any objects
        $checkUpdatedGroups[$groupName] = $objArr['@'];
    }
}

if($test)
    echo 'Checking groups in array: '.json_encode($checkUpdatedGroups).EOL;
$groups = $objHandler->checkGroupsUpdated($checkUpdatedGroups,$test);
foreach($params as $key=>$value){
    $upToDate = true;
    $objArr = json_decode($value,true);
    //Check if the group is up to date, in which case no need to check any objects
    if($key == '@'){
        $upToDate = false;
    }
    else{
        if($groups[$key] != 0)
            $upToDate = false;
    }
    //For each group that isn't up to date, add objects into the array we need to fetch
    if(!$upToDate){
        foreach($objArr as $objID => $timeUpdated){
            if($objID!='@')
                array_push($arr,[$objID,$timeUpdated,true]);
        }
    }
}
//Get all the objects requested
$objects = ($arr != [])? $objHandler->getObjects($arr, $test) : [];

foreach($objects as $key=>$object){
    if($key != 'Errors'){
        $objects['groupMap'][$key] = $object['Ob_Group'];
        $objects[$key] = $object['Object'];
    }
}

//Parse content if needed
if(function_exists('parseObjectContent')){
    foreach($objects as $id=>$content){
        if(gettype($id)=='integer'){
            parseObjectContent($objects[$id]);
        }
    }
}

$result = $objects;
