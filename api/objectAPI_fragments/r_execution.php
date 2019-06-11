<?php



$arr = [];          //Array of objects we need to get.
$checkUpdatedGroups = [];       //Groups we need to check for being updated

foreach($params as $groupName=>$value){
    if($groupName != '@'){
    //Check if the group is up to date, in which case no need to check any objects
        $checkUpdatedGroups[$groupName] = $value['@'];
    }
}

if($test)
    echo 'Checking groups in array: '.json_encode($checkUpdatedGroups).EOL;

$groups = $objHandler->checkGroupsUpdated($checkUpdatedGroups,['test'=>$test]);

foreach($params as $key=>$value){
    $upToDate = true;
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
        foreach($value as $objID => $timeUpdated){
            if($objID!='@')
                array_push($arr,[$objID,$timeUpdated]);
        }
    }
}

//Meant for extension by plugins
if(!isset($executionParameters))
    $executionParameters = [];
$executionParameters['test'] = $test;
//Get all the objects requested
$result = ($arr != [])? $objHandler->getObjects($arr, $executionParameters) : [];