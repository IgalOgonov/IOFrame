<?php

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