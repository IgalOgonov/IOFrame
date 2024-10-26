<?php



if(!\IOFrame\Util\ValidatorFunctions::validateSQLTableName($target)){
    if($test)
        echo 'Target must be a valid settings file name - which is a valid sql table name!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if($action == 'getSetting'){
    $expectedParam = 'settingName';
    if($params == null){
        if($test)
            echo 'Params must be set!';
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $expectedParam = null;

if($expectedParam){
    if(!isset($params[$expectedParam]) || !\IOFrame\Util\ValidatorFunctions::validateSQLKey($params[$expectedParam])){
        if($test)
            echo $expectedParam.' must be a valid setting name - which is a valid key!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}