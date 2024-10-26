<?php
//inputs
if(!isset($inputs['inputs']) || !\IOFrame\Util\PureUtilFunctions::is_json($inputs['inputs'])){
    if($test)
        echo 'Inputs must be set and a valid JSON'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$inputs['inputs'] = json_decode($inputs['inputs'],true);

if(count($inputs['inputs']) === 0){
    if($test)
        echo 'Inputs must be set and a valid JSON'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

foreach($inputs['inputs'] as $index => $inputArray){
    $cleanInput = [];

    $params = ['category','type'];

    foreach($params as $param){
        if(!isset($inputArray[$param])){
            if($test)
                echo 'Each input must contain a '.$param.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        elseif($param == 'type' && $inputArray[$param] === -1){
            $cleanInput[$param] = -1;
        }
        elseif(!filter_var($inputArray[$param],FILTER_VALIDATE_INT) && $inputArray[$param] !== 0){
            if($test)
                echo $param.' must be a non-negative number!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        else
            $cleanInput[$param] = $inputArray[$param];
    }

    $inputs['inputs'][$index] = $cleanInput;
}