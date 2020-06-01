<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';


//AUTH
if (!$auth->isAuthorized(0) && !$auth->hasAction(SET_USERS_AUTH) ){
    if($test)
        echo "User must be authorized to update users".EOL;
    exit(AUTHENTICATION_FAILURE);
}
//Validate username
if($inputs['username'] !== null && !\IOFrame\Util\validator::validateUsername($inputs['username'])){
    if($test)
        echo 'Username illegal!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//Validate email
if($inputs['email'] !== null && !filter_var($inputs['email'], FILTER_VALIDATE_EMAIL)){
    if($test)
        echo 'Email illegal!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//Parse active
if($inputs['active'] !== null)
    $inputs['active'] = $inputs['active']? 1 : 0;

//Validate normal integers
foreach(['id','created','bannedDate','suspiciousDate'] as $param){
    if($inputs[$param] !== null && !( $inputs[$param] === 0 || filter_var($inputs[$param], FILTER_VALIDATE_INT) ) ){
        if($test)
            echo $param.' has to be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    //Make sure ID is set
    elseif($param === 'id' && $inputs[$param] === null){
        if($test)
            echo $param.' has to be set!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}