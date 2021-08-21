<?php
if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

//Limit, max 500, min 1
if($inputs['limit'] !== null){
    if(!filter_var($inputs['limit'],FILTER_VALIDATE_INT)){
        if($test)
            echo 'limit must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $inputs['limit'] = 50;

//Offset
if($inputs['offset'] !== null){
    if(!filter_var($inputs['offset'],FILTER_VALIDATE_INT) && $inputs['limit']!==0){
        if($test)
            echo 'offset must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//createdAfter
if($inputs['createdAfter'] !== null){
    if(!filter_var($inputs['createdAfter'],FILTER_VALIDATE_INT) && $inputs['createdAfter']!==0){
        if($test)
            echo 'createdAfter must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//createdBefore
if($inputs['createdBefore'] !== null){
    if(!filter_var($inputs['createdBefore'],FILTER_VALIDATE_INT) && $inputs['createdBefore']!==0){
        if($test)
            echo 'createdBefore must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//changedAfter
if($inputs['changedAfter'] !== null){
    if(!filter_var($inputs['changedAfter'],FILTER_VALIDATE_INT) && $inputs['changedAfter']!==0){
        if($test)
            echo 'changedAfter must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//changedBefore
if($inputs['changedBefore'] !== null){
    if(!filter_var($inputs['changedBefore'],FILTER_VALIDATE_INT) && $inputs['changedBefore']!==0){
        if($test)
            echo 'changedBefore must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//includeRegex
if($inputs['includeRegex'] !== null){
    if(!preg_match('/'.REGEX_REGEX.'/',$inputs['includeRegex'])){
        if($test)
            echo 'Invalid includeRegex, must be letters, numbers, dots, and whitespace 1-128 characters only!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['includeRegex'] = str_replace('.','\.',$inputs['includeRegex']);
    $inputs['includeRegex'] = str_replace('-','\-',$inputs['includeRegex']);
    $inputs['includeRegex'] = str_replace('_','\_',$inputs['includeRegex']);
}

//excludeRegex
if($inputs['excludeRegex'] !== null){
    if(!preg_match('/'.REGEX_REGEX.'/',$inputs['excludeRegex'])){
        if($test)
            echo 'Invalid excludeRegex, must be letters, numbers, dots, and whitespace 1-128 characters only!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['excludeRegex'] = str_replace('.','\.',$inputs['excludeRegex']);
    $inputs['excludeRegex'] = str_replace('-','\-',$inputs['excludeRegex']);
    $inputs['excludeRegex'] = str_replace('_','\_',$inputs['excludeRegex']);
}