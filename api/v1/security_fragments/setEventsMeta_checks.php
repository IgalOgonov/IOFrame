<?php

//update & override
$inputs['override'] = $inputs['override'] === null? true : $inputs['override'];
$inputs['update'] = $inputs['update'] === null? false : $inputs['update'];

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
        elseif(!filter_var($inputArray[$param],FILTER_VALIDATE_INT) && $inputArray[$param] !== 0 && $inputArray[$param] !== -1){
            if($test)
                echo $param.' must be a non-negative number or -1!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        else
            $cleanInput[$param] = $inputArray[$param];
    }

    if(isset($inputArray['meta']) && !\IOFrame\Util\PureUtilFunctions::is_json($inputArray['meta'])){
        if($test)
            echo 'meta must be a valid JSON string!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    else
        $cleanInput['meta'] = $inputArray['meta'] ?? null;

    $inputs['inputs'][$index] = $cleanInput;
}