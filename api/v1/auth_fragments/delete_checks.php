<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

if($params == null){
    if($test)
        echo 'Params must be set!';
    exit(INPUT_VALIDATION_FAILURE);
}
if($action == 'deleteActions')
    $expectedParam = 'actions';
else
    $expectedParam = 'groups';

if(!is_array($params[$expectedParam])){
    if($test)
        echo $expectedParam.' must be an associative array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

foreach($params[$expectedParam] as $name){

    if(!\IOFrame\Util\validator::validateSQLKey($name)){
        if($test)
            echo 'Each member of '.$expectedParam.' must be a string of 1 to 256 characters!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
