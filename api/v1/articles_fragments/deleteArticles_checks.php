<?php
$retrieveParams = [
    'test'=>$test
];

$requiredAuth = REQUIRED_AUTH_OWNER;

if($inputs['permanentDeletion'])
    $requiredAuth = REQUIRED_AUTH_ADMIN;

if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['articles'])){
    if($test)
        echo 'articles must be a valid JSON array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
$inputs['articles'] = json_decode($inputs['articles'],true);
foreach($inputs['articles'] as $index => $value){
    if(!filter_var($value,FILTER_VALIDATE_INT)){
        if($test)
            echo 'Value #'.$index.' must be a valid integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}