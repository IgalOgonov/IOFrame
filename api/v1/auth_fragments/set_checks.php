<?php

if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

if($params == null){
    if($test)
        echo 'Params must be set!';
    exit(INPUT_VALIDATION_FAILURE);
}
if($action == 'setActions')
    $expectedParam = 'actions';
else
    $expectedParam = 'groups';

if(!is_array($params[$expectedParam])){
    if($test)
        echo $expectedParam.' must be an associative array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

foreach($params[$expectedParam] as $name=>$description){
    //Correction
    if(strtolower((string)$description) === 'null' || $description == ''){
        $params[$expectedParam][$name] = null;
    }

    if(!\IOFrame\Util\validator::validateSQLKey($name)){
        if($test)
            echo 'Each member of '.$expectedParam.' must be a valid string of 1 to 256 characters!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    if($description != null && (!gettype($description) == 'string' || strlen($description)>10000 || strlen($description) == 0) ){
        if($test)
            echo 'Each description that is not null must be a string of 1 to 10,000 characters!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
