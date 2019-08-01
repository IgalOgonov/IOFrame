<?php

/* Checks if the user is authorized to move a specific object, then if he's authorized to modify object/map assignments.
 * Returns true if the user can modify map/object assignments, false otherwise.
 * */
function checkObjectMapAuth($sesInfo, $auth){
    $res = false;
    //First, check if the rank of the user is 0
    if($sesInfo!== null &&$sesInfo['Auth_Rank'] == 0)
        $res = true;
    //If not, check USER_AUTH for the action 'Assign_Objects'
    if(!$res && $sesInfo!== null){
        $res = $auth->hasAction('ASSIGN_OBJECT_AUTH');
    }
    return $res;
}

if(!isset($params['id'])){
    if($test)
        echo 'You must send an object id to assign it to a map!';
    exit(INPUT_VALIDATION_FAILURE);
}
if(!isset($params['map'])){
    if($test)
        echo 'You must specify a map!';
    exit(INPUT_VALIDATION_FAILURE);
}
foreach($params as $key=>$value){
    switch($key){
        case 'obj':
            if(strlen($value)<1){
                if($test)
                    echo 'You need a non empty object if you want to create it!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
        case 'map':
            if(filter_var($value,FILTER_VALIDATE_URL)){
                if($test)
                    echo 'Illegal map name!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(strlen($value)<1){
                if($test)
                    echo 'You need a non empty map address if you want to create it!';
                exit(INPUT_VALIDATION_FAILURE);
            }
            break;
    }
}


//Get object ID
$id = $params['id'];
//Get page path
$map = $params['map'];
//Create/Remove assignment
//Prepare a new AuthHandler to check all objecTMap auth
if(!isset($auth))
    $auth = new IOFrame\Handlers\AuthHandler($settings, $defaultSettingsParams);
//Check if the user is autorized to modify map/object assignments in general
if(!checkObjectMapAuth($sesInfo, $auth)){
    if($test)
        echo 'User is not authorized to modify  map/object assignments! ';
    exit(AUTHENTICATION_FAILURE);
}

