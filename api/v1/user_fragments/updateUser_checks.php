<?php


//Validate username
if($inputs['username'] !== null && !\IOFrame\Util\ValidatorFunctions::validateUsername($inputs['username'])){
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

if($inputs['phone'] !== null){
    if(!preg_match('/'.PHONE_REGEX.'/',$inputs['phone'])){
        if($test)
            echo 'phone must match '.PHONE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//Validate normal integers
foreach(['id','created','bannedDate','lockedDate','suspiciousDate','active'] as $param){
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

//If a user requested to reset 2FA, it doesn't matter whether he requires it or not
if($inputs['reset2FA'])
    $inputs['require2FA'] = null;