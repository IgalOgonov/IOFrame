<?php

if($inputs['gallery'] === null){
    if($test)
        echo 'Gallery name must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!preg_match('/'.GALLERY_REGEX.'/',$inputs['gallery'])){
    if($test)
        echo 'Gallery name invalid!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//From, To
if($inputs['num1'] === null || $inputs['num2'] === null){
    if($test)
        echo 'Both num1 and num2 must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!filter_var($inputs['num1'],FILTER_VALIDATE_INT) && $inputs['num1'] != 0){
    if($test)
        echo 'num1 must be an integer!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if(!filter_var($inputs['num2'],FILTER_VALIDATE_INT) && $inputs['num2'] != 0){
    if($test)
        echo 'num2 must be an integer!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}