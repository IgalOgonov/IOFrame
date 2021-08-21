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

//Check if the user is autorized to modify map/object assignments in general
if(!checkObjectMapAuth($sesInfo, $auth)){
    if($test)
        echo 'User is not authorized to modify  map/object assignments! ';
    exit(AUTHENTICATION_FAILURE);
}