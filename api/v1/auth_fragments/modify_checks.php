<?php



if($params == null){
    if($test)
        echo 'Params must be set!';
    exit(INPUT_VALIDATION_FAILURE);
}


if($action == 'modifyUserActions' || $action == 'modifyUserGroups' )
    $expectedTarget = 'id';
else
    $expectedTarget = 'groupName';

if(!isset($params[$expectedTarget])){
    if($test)
        echo $expectedTarget.' must be an set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($expectedTarget == 'id'){
    if(!filter_var($params[$expectedTarget],FILTER_VALIDATE_INT)){
        if($test)
            echo 'ID must be a number!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    if(!\IOFrame\Util\ValidatorFunctions::validateSQLKey($params[$expectedTarget])){
        if($test)
            echo 'Group must be a string of 1 to 256 characters!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}


if($action == 'modifyUserActions' || $action == 'modifyGroupActions' )
    $expectedParam = 'actions';
else
    $expectedParam = 'groups';

if(!isset($params[$expectedParam]) || !is_array($params[$expectedParam])){
    if($test)
        echo $expectedParam.' must be an associative array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

foreach($params[$expectedParam] as $name => $assignment){
    //Potentially correct the assignment
    if($assignment == '0' || strtolower($assignment) == 'false')
        $params[$expectedParam][$name] = false;

    if(!\IOFrame\Util\ValidatorFunctions::validateSQLKey($name)){
        if($test)
            echo 'Each member of '.$expectedParam.' must be a string of 1 to 256 characters!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}



