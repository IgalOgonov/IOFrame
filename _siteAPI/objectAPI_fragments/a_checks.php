<?php

/* Checks if the user is authorized to move a specific object, then if he's authorized to modify object/page assignments.
 * Returns true if the user can modify page/object assignments, false otherwise.
 * */
function checkPageMapAuth($sesInfo, $auth){
    $res = false;
    //First, check if the rank of the user is 0
    if($sesInfo['Rank'] == 0)
        $res = true;
    //If not, check USER_AUTH for the action 'Assign_Objects'
    if(!$res){
        $res = $auth->hasAction('ASSIGN_OBJECT_AUTH');
    }
    return $res;
}

if(!isset($params['id'])){
    echo 'You must send an object id to assign it to a page!';
    return false;
}
if(!isset($params['page'])){
    echo 'You must specify a page!';
    return false;
}
foreach($params as $key=>$value){
    switch($key){
        case 'obj':
            if(strlen($value)<1){
                echo 'You need a non empty object if you want to create it!';
                return false;
            }
            break;
        case 'page':
            if(filter_var($value,FILTER_VALIDATE_URL)){
                echo 'Illegal page name!';
                return false;
            }
            if(strlen($value)<1){
                echo 'You need a non empty page address if you want to create it!';
                return false;
            }
            break;
        case '?':
            if(!($value==true || $value==false)){
                echo 'Illegal test value!';
                return false;
            }
            break;
    }
}


//Get object ID
$id = $params['id'];
//Get page path
$page = $params['page'];
//Create/Remove assignment
//Prepare a new authHandler to check all PageNap auth
if(!isset($auth))
    $auth = new IOFrame\authHandler($settings, $defaultSettingsParams);
//Check if the user is autorized to modify page/object assignments in general
if(!checkPageMapAuth($sesInfo, $auth)){
    if($test)
        echo 'User '.$sesInfo['ID'].' is not authorized to modify  page/object assignments! ';
    echo 3;
}

