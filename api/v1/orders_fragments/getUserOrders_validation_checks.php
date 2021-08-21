<?php
if(!defined('validator'))
    require __DIR__ . '/../../../IOFrame/Util/validator.php';

//ID
if($inputs['userID'] === null){
    $inputs['userID'] = $details['ID'];
}
else{
    if(!filter_var($inputs['userID'],FILTER_VALIDATE_INT)){
        if($test)
            echo 'each ID must be an integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//relationType
if($inputs['relationType'] !== null) {
    if (!\IOFrame\Util\validator::validateSQLKey($inputs['relationType'])) {
        if ($test)
            echo 'Invalid type!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//orderBy
if($inputs['orderBy'] !== null) {
    if (!\IOFrame\Util\validator::validateSQLKey($inputs['orderBy'])) {
        if ($test)
            echo 'Invalid orderBy!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//orderType
if($inputs['orderType'] !== null) {
    $inputs['orderType'] = $inputs['orderType'] || 0;
}
else
    $inputs['orderType'] = 0;


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