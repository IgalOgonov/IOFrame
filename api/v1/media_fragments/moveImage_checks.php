<?php
if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

if(!$inputs['remote'])
    $inputs['remote'] = false;
else
    $inputs['remote'] = true;

//Addresses
if($inputs['oldAddress'] === null || $inputs['newAddress'] === null){
    if($test)
        echo 'Both old and new address must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$valid = \IOFrame\Util\validator::validateRelativeFilePath($inputs['oldAddress']);
if($inputs['remote'])
    $valid = $valid || filter_var($inputs['oldAddress'],FILTER_VALIDATE_URL);
if(!$valid){
    if($test)
        echo 'Invalid old address!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$valid = \IOFrame\Util\validator::validateRelativeFilePath($inputs['newAddress']);
if($inputs['remote'])
    $valid = $valid || filter_var($inputs['newAddress'],FILTER_VALIDATE_URL);
if(!$valid){
    if($test)
        echo 'Invalid new address!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}