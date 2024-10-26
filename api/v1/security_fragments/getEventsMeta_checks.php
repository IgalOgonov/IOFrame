<?php

if($inputs['inputs'] === null)
    $inputs['inputs'] = [];
elseif(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['inputs'])){
    if($test)
        echo 'inputs must be a valid JSON string!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else
    $inputs['inputs'] = json_decode($inputs['inputs'],true);

foreach($inputs['inputs'] as $index => $inputsArray){
    $cleanInputs = [];

    if(isset($inputsArray['category'])){
        if(!filter_var($inputsArray['category'],FILTER_VALIDATE_INT) && $inputsArray['category'] !== 0){
            if($test)
                echo 'category must be an integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    else{
        if($test)
            echo 'category must be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $cleanInputs['category'] = $inputsArray['category'];

    if(isset($inputsArray['type'])){
        if(!filter_var($inputsArray['type'],FILTER_VALIDATE_INT) && $inputsArray['type'] !== 0 && $inputsArray['type'] !== -1){
            if($test)
                echo 'type must be an integer or -1!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    else
        $inputsArray['type'] = -1;
    $cleanInputs['type'] = $inputsArray['type'];

    $inputs['inputs'][$index] = $cleanInputs;
}

if(count($inputs['inputs']) === 0){

    if($inputs['limit'] !== null){
        if(!filter_var($inputs['limit'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'limit must be an integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    else
        $inputs['limit'] = 50;

    if($inputs['offset'] !== null){
        if(!filter_var($inputs['offset'],FILTER_VALIDATE_INT) && $inputs['offset'] !== 0 && $inputs['offset'] !== -1){
            if($test)
                echo 'offset must be an integer or -1!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    else
        $inputs['offset'] = 0;
}