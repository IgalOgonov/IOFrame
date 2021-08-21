<?php

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

//orderType
if($inputs['orderType'] !== null){
    if(!in_array($inputs['orderType'],['ASC','DESC'])){
        if($test)
            echo 'If orderType it must be ASC or DESC'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//orderBy
if($inputs['orderBy'] !== null){
    if(!in_array($inputs['orderType'],['Created','Last_Updated','Email','Country','City','Company_Name'])){
        if($test)
            echo 'If orderType it must be ASC or DESC'.EOL;
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
$regexArr = ['firstNameLike','emailLike','countryLike','cityLike','companyNameLike',
    'includeRegex','excludeRegex','fullNameLike','companyNameIDLike'];

foreach($regexArr as $paramName){
    if($inputs[$paramName] !== null){
        if(!preg_match('/'.REGEX_REGEX.'/',$inputs[$paramName])){
            if($test)
                echo 'Invalid '.$paramName.', must be letters, numbers, dots, and whitespace 1-128 characters only!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        $inputs[$paramName] = str_replace('.','\.',$inputs[$paramName]);
        $inputs[$paramName] = str_replace('-','\-',$inputs[$paramName]);
        $inputs[$paramName] = str_replace('_','\_',$inputs[$paramName]);
    }
}