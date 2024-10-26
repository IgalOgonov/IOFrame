<?php



$requiredAuth = REQUIRED_AUTH_OWNER;

$deletionParams = ['test' =>$test];

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Must be logged in to delete article blocks!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

if($inputs['articleId'] === null){
    if($test)
        echo 'articleId must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
if(!filter_var($inputs['articleId'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'articleId needs to be a valid int!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['deletionTargets'])){
    if($test)
        echo 'deletionTargets must be a valid JSON array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
$inputs['deletionTargets'] = json_decode($inputs['deletionTargets'],true);
foreach($inputs['deletionTargets'] as $index => $value){
    if( !( filter_var($value,FILTER_VALIDATE_INT) || ($value === 0 && !$inputs['permanentDeletion']) ) ){
        if($test)
            echo 'Value #'.$index.' in deletionTargets must be a valid integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
